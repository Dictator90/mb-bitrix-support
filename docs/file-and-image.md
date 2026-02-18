## Работа с файлами и изображениями

Этот раздел описывает слой `MB\Bitrix\File` и `MB\Bitrix\File\Image` — высокоуровневую обёртку над стандартным API Bitrix (`CFile`, `Bitrix\Main\File\Image` и др.) с дополнительными возможностями:

- унифицированная нормализация входных данных (включая `$_FILES`, ID файла и временные пути);
- валидация и поиск дубликатов файлов;
- сохранение и удаление файлов с учётом квоты;
- удобная работа с путями и данными файлов;
- обработка изображений на базе `spatie/image` с кэшированием результатов.

---

## Фасад файловой системы

Помимо объектного слоя `MB\Bitrix\File` в ядре доступен контейнерный сервис файловой системы (`filesystem`), который реализует интерфейс `MB\Filesystem\Contracts\Filesystem`.  
Для удобства к нему предоставлен статический фасад:

- класс: `MB\Bitrix\Support\Facades\Filesystem`;
- базируется на контейнере `MB\Bitrix\Foundation\Application` и биндинге `filesystem`.

Примеры:

```php
use MB\Bitrix\Support\Facades\Filesystem as Fs;

// Прочитать файл
$contents = Fs::get($_SERVER['DOCUMENT_ROOT'].'/local/data/example.json');

// Безопасно обновить содержимое
Fs::updateContent(
    $_SERVER['DOCUMENT_ROOT'].'/local/data/example.json',
    function (string $current): string {
        $data = $current !== '' ? json_decode($current, true) : [];
        $data['updated_at'] = time();
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
);

// Работа с директориями
if (!Fs::exists($dir)) {
    Fs::makeDirectory($dir);
}

$files = Fs::files($dir, true); // рекурсивный список файлов
```

Фасад является тонкой обёрткой вокруг DI-контейнера:

- в простом glue‑коде, CLI‑скриптах и шаблонах удобно использовать `Filesystem` как статический фасад;
- в доменных сервисах и инфраструктурных компонентах по‑прежнему рекомендуется **инъецировать** интерфейс `MB\Filesystem\Contracts\Filesystem` через контейнер.

---

## Класс `MB\Bitrix\File\FileService`

Файл: `src/File/FileService.php`  
Пространство имён: `MB\Bitrix\File`

`FileService` — основной фасад для работы с таблицей `b_file` и физическими файлами.

### Сохранение файла: `saveFile()`

```php
use MB\Bitrix\File\FileService;

$fileId = FileService::saveFile(
    $fileData,         // массив, совместимый с CFile/$_FILES
    'my_module/files', // относительный путь внутри upload
    $forceRandom = false,
    $skipExtension = false,
    $dirAdd = ''
);
```

**Что делает метод:**

- нормализует вход (`normalizeFileData()`), поддерживаются:
  - чистый массив `$_FILES` / `CFile::MakeFileArray`;
  - ID файла Bitrix;
  - путь к временному файлу;
- выполняет валидацию (`validateFile()`), при ошибке бросает `Main\SystemException`;
- подготавливает структуру данных `prepareFileData()` (имя файла, путь, размер, MIME, хеш и т.д.);
- ищет дубликат по размеру и хешу (`findDuplicate()` с использованием `FileDuplicateTable` и `FileHashTable`);
  - если найден — обрабатывает согласно политике (`handleDuplicate()`), как правило, переиспользует существующий файл;
- сохраняет физический файл (`savePhysicalFile()`);
- записывает строку в `b_file` (`saveToDatabase()`) и возвращает новый `FILE_ID`.

Возвращаемое значение:  
`int|null` — ID файла или `null`, если сохранение не удалось / имя пустое.

### Массовое сохранение: `saveFiles()`

```php
$results = FileService::saveFiles($_FILES['MULTI_UPLOAD'], 'my_module/files');

foreach ($results as $key => $result) {
    if ($result['success']) {
        $fileId = $result['fileId'];
        $fileData = $result['fileData']; // данные из b_file с обогащением
    } else {
        // $result['error'] содержит текст ошибки
    }
}
```

Метод оборачивает цикл по массиву файлов и:

- вызывает `saveFile()` для каждого элемента;
- перехватывает исключения и возвращает структуру:

```php
[
    'success'  => bool,
    'fileId'   => ?int,
    'fileData' => ?array, // результат getFileData()
    'error'    => ?string // при неуспехе
]
```

### Получение данных файла(ов)

- **`getFileData(int $fileId): ?array`**  
  Возвращает обогащённые данные по одному файлу с кешированием в статическом свойстве трейта `RememberCachable`.

- **`getFilesData(array $fileIds): array`**  
  Загружает несколько файлов за один запрос:
  - проверяет кеш и собирает IDs, которых нет в кеше;
  - делает запрос `FileTable::query()->setSelect(['*', 'HASH.*'])->whereIn('ID', $idsToFetch)`;  
  - обогащает данные (`enrichFileData()`), кладёт в кеш и возвращает ассоциативный массив `[$id => $fileArray]`.

- **`getFilesByFilter(array $filter = [], array $order = ['ID' => 'DESC'], int $limit = 50, int $offset = 0)`**  
  Возвращает `Main\ORM\Query\Result` по произвольному фильтру.

Пример:

```php
use MB\Bitrix\File\FileService;

$result = FileService::getFilesByFilter(
    ['CONTENT_TYPE' => 'image/%'],
    ['ID' => 'DESC'],
    20
);

while ($file = $result->fetch()) {
    // $file содержит поля b_file + HASH_*
}
```

### Обновление и удаление

- **`updateDescription(int $fileId, string $description): bool`**  
  Обновляет поле `DESCRIPTION` напрямую через SQL и сбрасывает кеш файла.

- **`deleteFile(int $fileId): bool`**

  - загружает данные файла;
  - удаляет физический файл (`deletePhysicalFile()`);
  - помечает запись в `FileDuplicateTable` как удалённую и удаляет хеш из `FileHashTable`;
  - удаляет строку из `b_file`;
  - очищает кеш и обновляет квоту (`CDiskQuota::updateDiskQuota()`), если включена.

### Работа с путями

- **`getFilePath(int $fileId): ?string`**  
  Возвращает абсолютный путь к файлу по ID или `null`, если путь не найден.

- **`getFilePathFromArray(array $file): ?string`**  
  Универсальный метод для получения абсолютного пути:
  - если есть `tmp_name` и файл существует — возвращает его;
  - если есть `SRC` — склеивает с `DOCUMENT_ROOT`;
  - если есть `SUBDIR` и `FILE_NAME` — собирает путь внутри каталога upload;
  - если есть `ID` — делает `getFilePath(ID)`.

### Нормализация входных данных: `makeFileArray()`

```php
// Из ID файла
$fileArray = FileService::makeFileArray(123);

// Из временного пути
$fileArray = FileService::makeFileArray('/tmp/uploaded-file.tmp');

// Из исходного массива $_FILES
$fileArray = FileService::makeFileArray($_FILES['PHOTO']);
```

Метод приводит разные варианты входа к единому виду, ожидаемому Bitrix/`FileService` (ключи `tmp_name`, `name`, `type`, `size`, `error` и т.д.), и может участвовать в обработке событий.

---

## Работа с изображениями

Подсистема `MB\Bitrix\File\Image` строится поверх `spatie/image` и использует переключаемый кэш (`DatabaseImageCache`, `NullImageCache`) и операции (`SpatieImageOperation`).

Ключевые классы:

- `MB\Bitrix\File\Image\Image` — низкоуровневая одноразовая обёртка над `Spatie\Image\Image`;
- `MB\Bitrix\File\Image\ImageProcessor` — процессор с кэшированием результатов обработки;
- `MB\Bitrix\File\Image\ImageBuilder` — fluent‑билдер для бизнес-сценариев обработки;
- `MB\Bitrix\File\Image\DatabaseImageCache` / `NullImageCache` — реализации `ImageCache`;
- `MB\Bitrix\File\Image\Operations\SpatieImageOperation` — обёртка для операций Spatie.

### Низкоуровневый класс `Image`

Файл: `src/File/Image.php`  
Назначение: быстро выполнить одну или несколько операций `Spatie\Image\Image` без кэширования и без сохранения в Bitrix.

```php
use MB\Bitrix\File\Image;

// Из ID файла Bitrix
$img = new Image(123);

// Например, ресайз и получение base64
$base64 = $img
    ->resize(800, 600)
    ->quality(85)
    ->base64('jpeg');
```

Конструктор принимает:

- массив данных файла (`b_file`/`CFile`);
- ID файла Bitrix;
- строковый путь.

Путь к реальному файлу определяется через `FileService::getFilePathFromArray()` или `SRC`.  
Все методы Spatie доступны через магический `__call()` (см. phpdoc в классе).

### Процессор `ImageProcessor`

Файл: `src/File/Image/ImageProcessor.php`  
Реализует интерфейс `MB\Bitrix\Contracts\File\ImageProcessor`.

Основные методы:

- **`process(int $fileId, array $operations, ?string $format = null, int $quality = 95): int`**
  - строит ключ кэша `generateCacheKey()` по:
    - ID файла, времени изменения и размеру файла;
    - массиву операций (`name` + `params` у `SpatieImageOperation`);
    - формату, качеству и типу процессора;
  - проверяет кэш через `getCached()`; если найден — сразу возвращает ID обработанного файла;
  - иначе вызывает `executeProcessing()`, сохраняет результат и заносит в кэш.

- **`getCached(...)`** — прямой доступ к кэшу по тем же параметрам.

Внутри `executeProcessing()`:

- загружает оригинальный файл по ID (`FileService::getFilePath()`, `SpatieImage::load()`),
- последовательно применяет операции `SpatieImageOperation::apply()` к объекту `Spatie\Image\Image`,
- выставляет формат и качество,
- сохраняет временный файл и затем сохраняет его в Bitrix через `FileService::saveFile()`, создавая новый `FILE_ID`.

### Кэш изображений

Интерфейс: `MB\Bitrix\Contracts\File\ImageCache`  
Реализации:

- **`DatabaseImageCache`**
  - хранит результаты в таблице `CacheTable` (`src/File/Image/Storage/CacheTable.php`);
  - при получении:
    - загружает ORM-объект по ключу;
    - проверяет существование физического файла через `FileService` и `Filesystem`;
    - при отсутствии файла — удаляет запись и возвращает `null`.
  - при установке — добавляет строку с `ORIGINAL_FILE_ID`, `FILE_ID`, `CACHE_KEY`;
  - умеет очищать кэш по исходному файлу (`clearForFile()`).

- **`NullImageCache`**
  - заглушка, всегда возвращающая `null` и ничего не сохраняющая — удобно для отключения кэширования.

### Fluent‑билдер `ImageBuilder`

Файл: `src/File/Image/ImageBuilder.php`

`ImageBuilder` — основной инструмент для бизнес-кода: позволяет декларативно описать цепочку операций, формат и качество, а затем получить:

- ID обработанного файла;
- массив данных файла;
- готовый URL.

Примеры:

```php
use MB\Bitrix\File\Image\ImageBuilder;

// Превью 300x300 с кэшем
$preview = ImageBuilder::create($fileId)
    ->fit('contain', 300, 300)
    ->quality(85)
    ->get();          // массив с данными обработанного файла

// Только URL
$url = ImageBuilder::create($fileId)
    ->resize(1024, 768, ['preserveAspectRatio' => true])
    ->quality(80)
    ->getUrl();

// ID файла
$processedId = ImageBuilder::create($fileId)
    ->greyscale()
    ->format('webp')
    ->get(false);     // вернёт int
```

Ключевые методы:

- магический `__call()` — любое имя метода Spatie (`resize`, `crop`, `watermark` и т.д.) превращается во внутреннюю `SpatieImageOperation`;
- `format(string $format)` — целевой формат (`jpg`, `png`, `webp`, …);
- `quality(int $quality)` — качество (1–100), значение ограничивается границами;
- `custom(\Closure $callback, string $name = 'custom')` — произвольная операция над `Spatie\Image\Image`;
- `get(bool $returnArray = true)` — выполняет обработку через `ImageProcessor::process()` и возвращает либо массив файла, либо `FILE_ID`;
- `getUrl(?int $fileId = null)` — возвращает `SRC` файла;
- `clearCache()` — очищает кэш для исходного файла;
- пресеты `preset()` / `applyPreset()` — готовые сценарии вроде `thumbnail_300`, `preview_1024`, `avatar_square_256`.

### Операции `SpatieImageOperation`

Файл: `src/File/Image/Operations/SpatieImageOperation.php`

Это небольшой объект, инкапсулирующий:

- имя операции (`getName()`);
- параметры (`getParams()`);
- callback, применяющий операцию к `Spatie\Image\Image`.

Поставляются фабрики:

- `create($method, $arguments)` — обёртка вокруг произвольного метода Spatie;
- `createCustom(\Closure $callback, string $name = 'custom')` — кастомная операция;
- укороченные методы (`resize`, `width`, `height`, `fit`, `crop`, `optimize`, `quality`, `format`, `watermark`, `brightness`, `greyscale` и т.п.);
- магический `__callStatic()` — позволяет вызывать любой метод Spatie по имени.

---

## Типичные сценарии использования

### 1. Сохранить загруженный файл и получить его URL

```php
use MB\Bitrix\File\FileService;

$fileId = FileService::saveFile($_FILES['PHOTO'], 'my_module/photos');

if ($fileId) {
    $file = FileService::getFileData($fileId);
    $url  = $file['SRC'] ?? '';
}
```

### 2. Создать превью изображения с кэшем

```php
use MB\Bitrix\File\Image\ImageBuilder;

$previewUrl = ImageBuilder::create($fileId)
    ->preset('thumbnail_300') // 300x300, contain, качество 85
    ->getUrl();
```

При повторных вызовах для того же набора параметров будет использован результат из кэша (таблица `CacheTable`).

### 3. Массовая обработка списка изображений

```php
use MB\Bitrix\File\Image\BatchImageProcessor;

$processor = new BatchImageProcessor();

// Примерный сценарий:
// $processor->processMany($fileIds, $operations, $format, $quality);
// Подробности см. реализацию BatchImageProcessor в src/File/Image/BatchImageProcessor.php.
```

Рекомендуется использовать `BatchImageProcessor` для задач, где:

- есть большой список `FILE_ID`;
- одна и та же цепочка операций применяется ко всем изображениям;
- важно минимизировать количество обращений к диску и БД.

### 4. Отключить кэш изображений

```php
use MB\Bitrix\File\Image\ImageBuilder;
use MB\Bitrix\File\Image\ImageProcessor;
use MB\Bitrix\File\Image\NullImageCache;

$processor = new ImageProcessor(new NullImageCache());

$builder = new ImageBuilder($fileId, $processor);
$url = $builder
    ->resize(800, 600)
    ->quality(90)
    ->getUrl();
```

В этом случае результат обработки всегда пересчитывается заново и не сохраняется в таблицу кэша.

