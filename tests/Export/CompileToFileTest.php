<?php

namespace Pux\Tests\Export;

use PHPUnit\Framework\TestCase;
use Pux\Mux;
use Pux\MuxCompiler;

/**
 * Route cache files: Mux::compile() and MuxCompiler::compile() write a PHP file that returns
 * a Mux; requiring it yields a Mux that dispatches the compiled routes. Files are written to
 * sys_get_temp_dir() and removed in tearDown().
 */
final class CompileToFileTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function tempFile(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'pux-test-');
        self::assertIsString($file, 'tempnam() failed');
        $this->files[] = $file;

        return $file;
    }

    private static function requireMux(string $file): Mux
    {
        $mux = require $file;
        self::assertInstanceOf(Mux::class, $mux);

        return $mux;
    }

    private function compiledProductMux(): Mux
    {
        $mux = new Mux();
        $mux->add('/product/:id', ['ProductController', 'itemAction']);
        $mux->add('/product', ['ProductController', 'listAction']);
        $mux->add('/foo', ['ProductController', 'fooAction']);
        $mux->add('/bar', ['ProductController', 'barAction']);
        $mux->add('/', ['ProductController', 'indexAction']);
        $file = $this->tempFile();
        $mux->compile($file);

        return self::requireMux($file);
    }

    public function test_mux_compile_writes_file_returning_mux_that_dispatches_static_routes(): void
    {
        $mux = $this->compiledProductMux();

        self::assertSame([false, '/foo', ['ProductController', 'fooAction'], []], $mux->dispatch('/foo'));
        self::assertSame([false, '/product', ['ProductController', 'listAction'], []], $mux->dispatch('/product'));
        self::assertSame([false, '/', ['ProductController', 'indexAction'], []], $mux->dispatch('/'));
        self::assertSame([false, '/bar', ['ProductController', 'barAction'], []], $mux->dispatch('/bar'));
    }

    public function test_mux_compile_writes_file_returning_mux_that_dispatches_pcre_route(): void
    {
        $route = $this->compiledProductMux()->dispatch('/product/10');

        self::assertTrue($route[0]);
        self::assertSame('/product/:id', $route[3]['pattern']);
        self::assertSame('10', $route[3]['vars']['id']);
    }

    public function test_compiler_merges_loaded_route_files_into_one_dispatching_mux(): void
    {
        $hello = new Mux();
        $hello->add('/hello/:name', ['FooController', 'index']);
        $helloFile = $this->tempFile();
        $hello->compile($helloFile);
        $bye = new Mux();
        $bye->add('/bye/:name', ['FooController', 'index']);
        $byeFile = $this->tempFile();
        $bye->compile($byeFile);
        $mergedFile = $this->tempFile();

        $compiler = new MuxCompiler();
        $compiler->load($helloFile);
        $compiler->load($byeFile);
        $compiler->compile($mergedFile);
        $merged = self::requireMux($mergedFile);

        self::assertSame('/hello/:name', $merged->dispatch('/hello/John')[3]['pattern']);
        self::assertSame('/bye/:name', $merged->dispatch('/bye/John')[3]['pattern']);
    }

    /**
     * Failed before the R4 fix with Error 'Class "Pux\Exception" not found' (Exception was not
     * imported in namespace Pux).
     *
     * @see PLAN-port-php82.md R4
     */
    public function test_merge_with_callback_validation_rejects_route_to_unknown_controller(): void
    {
        $mux = new Mux();
        $mux->any('/x', ['NoSuchController', 'index']);
        $compiler = new MuxCompiler();
        $compiler->callbackValidation = true;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Controller NoSuchController does not exist.');

        $compiler->merge($mux);
    }
}
