<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class WassermelderValidationTest extends TestCaseSymconValidation
{
    public function testValidateWassermelder(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateWassermelderModule(): void
    {
        $this->validateModule(__DIR__ . '/../Wassermelder');
    }
}