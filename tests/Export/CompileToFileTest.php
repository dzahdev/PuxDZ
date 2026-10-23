<?php

declare(strict_types=1);

namespace Pux\Tests\Export;

use PHPUnit\Framework\TestCase;
use Pux\Exception\CacheWriteFailed;
use Pux\Exception\InvalidCallback;
use Pux\Mux;
use Pux\MuxCompiler;
use Pux\Tests\Fixtures\ConstructorSideEffectController;
use Pux\Tests\Fixtures\PlainResource;
use Pux\Tests\Fixtures\PrefixedAction;

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
     * Failed before the L17 fix: load() returned true (the result of merge()), not the Mux its
     * docblock named.
     *
     * @see PLAN-port-php82.md L17
     */
    public function test_compiler_load_returns_merged_mux_dispatching_loaded_and_earlier_routes(): void
    {
        $hello = new Mux();
        $hello->add('/hello/:name', ['FooController', 'index']);
        $helloFile = $this->tempFile();
        $hello->compile($helloFile);
        $bye = new Mux();
        $bye->add('/bye', ['FooController', 'index']);
        $byeFile = $this->tempFile();
        $bye->compile($byeFile);

        $compiler = new MuxCompiler();
        $compiler->load($helloFile);
        $merged = $compiler->load($byeFile);

        self::assertInstanceOf(Mux::class, $merged);
        self::assertSame('/hello/:name', $merged->dispatch('/hello/John')[3]['pattern']);
        self::assertSame([false, '/bye', ['FooController', 'index'], []], $merged->dispatch('/bye'));
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

    /**
     * Failed before the L18 fix: validation created the controller to call method_exists(), so
     * the constructor ran (here it throws).
     *
     * @see PLAN-port-php82.md L18
     */
    public function test_merge_with_callback_validation_accepts_route_without_instantiating_controller(): void
    {
        $mux = new Mux();
        $mux->any('/side', [ConstructorSideEffectController::class, 'indexAction']);
        $compiler = new MuxCompiler();
        $compiler->callbackValidation = true;

        $compiler->merge($mux);

        self::assertSame(
            [false, '/side', [ConstructorSideEffectController::class, 'indexAction'], []],
            $compiler->get()->dispatch('/side'),
        );
    }

    /**
     * Failed before the L18 fix with the exception of the constructor instead of InvalidCallback.
     *
     * @see PLAN-port-php82.md L18
     */
    public function test_merge_with_callback_validation_rejects_route_to_unknown_method(): void
    {
        $mux = new Mux();
        $mux->any('/x', [ConstructorSideEffectController::class, 'missingAction']);
        $compiler = new MuxCompiler();
        $compiler->callbackValidation = true;

        $this->expectException(InvalidCallback::class);
        $this->expectExceptionMessage('Method missingAction not found in controller ' . ConstructorSideEffectController::class . '.');

        $compiler->merge($mux);
    }

    /**
     * Failed before the L34 fix with TypeError: class_exists() was called with the object.
     *
     * @see PLAN-port-php82.md L34
     */
    public function test_merge_with_callback_validation_accepts_object_and_existing_method(): void
    {
        $mux = new Mux();
        $mux->any('/x', [new PlainResource(), 'loadAction']);
        $compiler = new MuxCompiler();
        $compiler->callbackValidation = true;

        $compiler->merge($mux);

        self::assertSame('loadAction', $compiler->get()->dispatch('/x')[2][1]);
    }

    /**
     * @see PLAN-port-php82.md L34
     */
    public function test_merge_with_callback_validation_rejects_object_and_unknown_method(): void
    {
        $mux = new Mux();
        $mux->any('/x', [new PlainResource(), 'missingAction']);
        $compiler = new MuxCompiler();
        $compiler->callbackValidation = true;

        $this->expectException(InvalidCallback::class);
        $this->expectExceptionMessage('Method missingAction not found in controller ' . PlainResource::class . '.');

        $compiler->merge($mux);
    }

    /**
     * @see PLAN-port-php82.md L34
     */
    public function test_merge_with_callback_validation_accepts_closure_and_invokable_object(): void
    {
        $mux = new Mux();
        $mux->any('/closure', static fn (): string => 'closure');
        $mux->any('/invokable', new PrefixedAction('order'));
        $compiler = new MuxCompiler();
        $compiler->callbackValidation = true;

        $compiler->merge($mux);

        self::assertSame(2, $compiler->get()->length());
    }

    private static function pathInMissingDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/pux-test-missing-' . bin2hex(random_bytes(8));
        self::assertDirectoryDoesNotExist($directory);

        return $directory . '/routes.php';
    }

    /**
     * Failed before the L36 fix: file_put_contents() into a missing directory emitted a warning
     * and Mux::compile() returned false.
     *
     * @see PLAN-port-php82.md L36
     */
    public function test_mux_compile_into_missing_directory_throws_cache_write_failed_naming_file(): void
    {
        $mux = new Mux();
        $mux->add('/product/:id', ['ProductController', 'itemAction']);
        $file = self::pathInMissingDirectory();

        $this->expectException(CacheWriteFailed::class);
        $this->expectExceptionMessage($file);

        $mux->compile($file);
    }

    /**
     * Failed before the L36 fix: MuxCompiler::compile() returned false with a warning.
     *
     * @see PLAN-port-php82.md L36
     */
    public function test_compiler_compile_into_missing_directory_throws_cache_write_failed_naming_file(): void
    {
        $mux = new Mux();
        $mux->add('/product', ['ProductController', 'listAction']);
        $compiler = new MuxCompiler();
        $compiler->merge($mux);
        $file = self::pathInMissingDirectory();

        $this->expectException(CacheWriteFailed::class);
        $this->expectExceptionMessage($file);

        $compiler->compile($file);
    }
}
