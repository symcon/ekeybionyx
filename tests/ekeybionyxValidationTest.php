<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class ekeybionyxValidationTest extends TestCaseSymconValidation
{
    public function testValidateekeybionyx(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }    public function testValidateekeyCloud(): void
    {
        $this->validateModule(__DIR__ . '/../ekeyCloud');
    }    public function testValidateekeyConfigurator(): void
    {
        $this->validateModule(__DIR__ . '/../ekeyConfigurator');
    }    public function testValidateekeySystem(): void
    {
        $this->validateModule(__DIR__ . '/../ekeySystem');
    }
}