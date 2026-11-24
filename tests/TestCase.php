<?php

namespace Tests;

use App\Sekolah;
use App\Support\ChatDefault;
use App\Support\SekolahAktif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Sekolah bawaan untuk tes (tenant). Semua data uji dan request tanpa ?sekolah= memakai sekolah ini. */
    protected ?Sekolah $sekolah = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Tes yang memakai database: sediakan 1 sekolah bawaan + konteks tenant, sama seperti request nyata ke alamat bawaan.
        if (in_array(RefreshDatabase::class, class_uses_recursive($this), true)) {
            $this->sekolah = Sekolah::create(['nama' => 'Sekolah Uji', 'subdomain' => 'uji', 'jenjang' => 'SMA', 'status' => 'aktif', 'tanpa_batas' => true]);
            config(['sekolah.default_subdomain' => 'uji']);
            SekolahAktif::instance()->set($this->sekolah);
            ChatDefault::pasang($this->sekolah); // FAQ dan template balasan bawaan
        }
    }
}
