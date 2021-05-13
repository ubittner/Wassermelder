<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class WassermelderValidationTest extends TestCaseSymconValidation
{
    public function testValidateLibrary_Wassermelder(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateModule_Wassermelder(): void
    {
        $this->validateModule(__DIR__ . '/../Wassermelder');
    }
}