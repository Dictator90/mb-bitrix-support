<?php
namespace MB\Bitrix\File\Image;

use Bitrix\Main\Loader;
use MB\Bitrix\File\FileService;
use Spatie\Image\Drivers\ImageDriver;
use Spatie\Image\Enums\AlignPosition;
use Spatie\Image\Enums\BorderType;
use Spatie\Image\Enums\ColorFormat;
use Spatie\Image\Enums\Constraint;
use Spatie\Image\Enums\CropPosition;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Enums\FlipDirection;
use Spatie\Image\Enums\Orientation;
use Spatie\Image\Enums\Unit;
use Spatie\Image\Size;
use Spatie\ImageOptimizer\OptimizerChain;

/**
 * Class Image
 *
 * Низкоуровневая обёртка над Spatie\\Image\\Image для одноразовых операций
 * без кэширования и сохранения результата в Bitrix.
 *
 * Для бизнес‑логики и повторно используемых вариантов обработки
 * рекомендуется использовать `ImageBuilder`.
 *
 * @method array<string, mixed> exif()
 * @method int getWidth()
 * @method int getHeight()
 * @method Size getSize()
 * @method mixed pickColor(int $x, int $y, ColorFormat $colorFormat)
 * @method string base64(string $imageFormat = 'jpeg', bool $prefixWithFormat = true)
 * @method \Spatie\Image\Image width(int $width, array $constraints = [Constraint::PreserveAspectRatio])
 * @method \Spatie\Image\Image height(int $height, array $constraints = [Constraint::PreserveAspectRatio])
 * @method \Spatie\Image\Image quality(int $quality)
 * @method \Spatie\Image\Image format(string $format)
 * @method \Spatie\Image\Image optimize(?OptimizerChain $optimizerChain = null)
 * @method \Spatie\Image\Image resize(int $width, int $height, array $constraints = [])
 * @method \Spatie\Image\Image resizeCanvas(?int $width = null, ?int $height = null, ?AlignPosition $position = null, bool $relative = false, string $backgroundColor = '#000000')
 * @method \Spatie\Image\Image manualCrop(int $width, int $height, ?int $x = null, ?int $y = null)
 * @method \Spatie\Image\Image crop(int $width, int $height, CropPosition $position = CropPosition::Center)
 * @method \Spatie\Image\Image focalCrop(int $width, int $height, ?int $cropCenterX = null, ?int $cropCenterY = null)
 * @method \Spatie\Image\Image focalCropAndResize(int $width, int $height, ?int $cropCenterX = null, ?int $cropCenterY = null)
 * @method \Spatie\Image\Image flip(FlipDirection $flip)
 * @method \Spatie\Image\Image brightness(int $brightness)
 * @method \Spatie\Image\Image gamma(float $gamma)
 * @method \Spatie\Image\Image contrast(float $level)
 * @method \Spatie\Image\Image blur(int $blur)
 * @method \Spatie\Image\Image colorize(int $red, int $green, int $blue)
 * @method \Spatie\Image\Image greyscale()
 * @method \Spatie\Image\Image sepia()
 * @method \Spatie\Image\Image sharpen(float $amount)
 * @method \Spatie\Image\Image pixelate(int $pixelate = 50)
 * @method \Spatie\Image\Image border(int $width, BorderType $type, string $color = '000000')
 * @method \Spatie\Image\Image background(string $color)
 * @method \Spatie\Image\Image overlay(ImageDriver $bottomImage, ImageDriver $topImage, int $x, int $y)
 * @method \Spatie\Image\Image orientation(?Orientation $orientation = null)
 * @method \Spatie\Image\Image fit(Fit $fit, ?int $desiredWidth = null, ?int $desiredHeight = null, bool $relative = false, string $backgroundColor = '#ffffff')
 * @method \Spatie\Image\Image insert(ImageDriver|string $otherImage, AlignPosition $position = AlignPosition::Center, int $x = 0, int $y = 0, int $alpha = 100)
 * @method \Spatie\Image\Image watermark(ImageDriver|string $watermarkImage, AlignPosition $position = AlignPosition::BottomRight, int $paddingX = 0, int $paddingY = 0, Unit $paddingUnit = Unit::Pixel, int $width = 0, Unit $widthUnit = Unit::Pixel, int $height = 0, Unit $heightUnit = Unit::Pixel, Fit $fit = Fit::Contain, int $alpha = 100)
 * @method \Spatie\Image\Image text(string $text, int $fontSize, string $color = '000000', int $x = 0, int $y = 0, int $angle = 0, string $fontPath = '', int $width = 0)
 */
class Image
{
    protected array $data;
    protected \Spatie\Image\Image $image;

    public function __construct(array|string|int $target)
    {
        if (is_array($target)) {
            $this->data = $target;
        } else {
            $this->data = FileService::getFileData($target) ?? [];
        }

        $path = FileService::getFilePathFromArray($this->data)
            ?? (isset($this->data['SRC']) ? Loader::getDocumentRoot() . $this->data['SRC'] : null);
        if (!$path) {
            throw new \InvalidArgumentException('Unable to resolve file path');
        }
        $this->image = \Spatie\Image\Image::load($path);
    }

    public function __call(string $name, array $arguments)
    {
        if (method_exists($this->image, $name)) {
            return $this->image->$name(...$arguments);
        }

        // Позволяем расширять класс собственными методами
        return $this->$name(...$arguments);
    }
}