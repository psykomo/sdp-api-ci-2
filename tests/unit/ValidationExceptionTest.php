<?php

namespace Tests\Unit;

use App\Exceptions\ValidationException;
use CodeIgniter\Test\CIUnitTestCase;
use DomainException;

class ValidationExceptionTest extends CIUnitTestCase
{
    public function testCarriesFieldErrorsAndIsDomainException(): void
    {
        $e = new ValidationException('Validation failed.', [
            'email' => 'Required',
        ]);

        $this->assertInstanceOf(DomainException::class, $e);
        $this->assertSame('Validation failed.', $e->getMessage());
        $this->assertSame(['email' => 'Required'], $e->getErrors());
    }
}
