<?php

namespace Tests\Unit;

use App\Services\PlacaService;
use PHPUnit\Framework\TestCase;

class PlacaServiceTest extends TestCase
{
    public function test_normaliza_mayusculas_sin_guiones_ni_espacios(): void
    {
        $this->assertSame('2481KLM', PlacaService::normalizar(' 2481-klm '));
        $this->assertSame('194UXI', PlacaService::normalizar('194 uxi'));
    }

    public function test_formato_boliviano(): void
    {
        $this->assertTrue(PlacaService::formatoValido('2481KLM'));
        $this->assertTrue(PlacaService::formatoValido('194UXI'));
        $this->assertFalse(PlacaService::formatoValido('ABC1234'));
        $this->assertFalse(PlacaService::formatoValido('24KLM'));
    }

    public function test_corrige_por_posicion(): void
    {
        $this->assertSame('1701NAC', PlacaService::corregirFormato('1701N4C'));
        $this->assertSame('1703XFY', PlacaService::corregirFormato('I7O3XFY'));
    }

    public function test_extrae_placas_de_texto_libre(): void
    {
        $this->assertSame(['2481KLM'], PlacaService::extraerDeTexto('¿pasó la 2481-klm hoy?'));
        $this->assertSame(['1151DKL', '194UXI'], PlacaService::extraerDeTexto('compara 1151 DKL con 194UXI'));
        $this->assertSame([], PlacaService::extraerDeTexto('hola, ¿cómo estás?'));
    }

    public function test_distancia_ocr_pondera_confusiones(): void
    {
        $this->assertSame(0.5, PlacaService::distancia('1985CXX', '1935CXX'));   // 3↔8
        $this->assertSame(0.5, PlacaService::distancia('1O78LPS', '1078LPS'));   // O↔0
        $this->assertSame(1.0, PlacaService::distancia('1078LPS', '1078LPX'));
        $this->assertTrue(PlacaService::equivalenteOcr('1483AKF', '1483AXF'));
        $this->assertFalse(PlacaService::equivalenteOcr('4917YBP', '1917YBP'));
    }
}
