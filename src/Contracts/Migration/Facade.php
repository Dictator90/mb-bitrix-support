<?php
namespace MB\Bitrix\Contracts\Migration;

use Bitrix\Main\Result;

interface Facade
{
    public function up(): Result;
    public function down(): Result;

    public function upStorages(): Result;
    public function downStorages(): Result;

    public function upAgents(): Result;
    public function downAgents(): Result;

    public function upEvents(): Result;
    public function downEvents(): Result;

    public function upFiles(): Result;
    public function downFiles(): Result;
}