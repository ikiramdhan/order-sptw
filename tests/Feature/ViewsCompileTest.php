<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ViewsCompileTest extends TestCase
{
    /** Hasil kompilasi semua tampilan Blade harus berupa PHP yang valid (menangkap @endif yang menempel di kata, dsb). */
    public function test_all_blade_views_compile_to_valid_php(): void
    {
        $blade = app('blade.compiler');
        $checked = 0;

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $php = $blade->compileString(file_get_contents($file->getPathname()));
            try {
                token_get_all($php, TOKEN_PARSE);
            } catch (\ParseError $e) {
                $this->fail($file->getRelativePathname().': '.$e->getMessage());
            }
            $checked++;
        }

        $this->assertGreaterThan(20, $checked);
    }
}
