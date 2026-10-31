<?php

namespace App\Shared\Gmail\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;

#[Signature('gmail-api:make-mailable
    {name : The name of the mailable class}
    {--view= : Also create a Blade view for the mailable (optionally give its name)}
    {--f|force : Create the class even if it already exists}')]
#[Description('Create a new GMailable class')]
class MakeGmailable extends GeneratorCommand
{
    protected $type = 'GMailable';

    protected function configureDefaults(): void
    {
        // false = option not given, null = given without a value (use the default view name).
        $this->getDefinition()->getOption('view')->setDefault(false);
    }

    public function handle(): int
    {
        if (parent::handle() === false && ! $this->option('force')) {
            return self::FAILURE;
        }

        if ($this->wantsView()) {
            $this->writeView();
        }

        return self::SUCCESS;
    }

    protected function writeView(): void
    {
        $path = $this->viewPath(str_replace('.', '/', $this->getView()).'.blade.php');

        if ($this->files->exists($path) && ! $this->option('force')) {
            $this->components->error(sprintf('View [%s] already exists.', $path));

            return;
        }

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, str_replace(
            '{{ subject }}',
            $this->subject(),
            $this->files->get($this->resolveStubPath('gmailable-view.blade.stub'))
        ));

        $this->components->info(sprintf('View [%s] created successfully.', $path));
    }

    protected function buildClass($name): string
    {
        return str_replace(
            ['{{ subject }}', '{{ view }}'],
            [$this->subject(), $this->getView()],
            parent::buildClass($name)
        );
    }

    /**
     * The view name: the --view value, or "mail.gmail.<kebab-cased name>".
     */
    protected function getView(): string
    {
        if (is_string($this->option('view')) && $this->option('view') !== '') {
            return $this->option('view');
        }

        return 'mail.gmail.'.collect(explode('/', str_replace('\\', '/', $this->getNameInput())))
            ->map(fn (string $part) => Str::kebab($part))
            ->implode('.');
    }

    protected function subject(): string
    {
        return Str::headline(class_basename(str_replace('/', '\\', $this->getNameInput())));
    }

    protected function wantsView(): bool
    {
        return $this->option('view') !== false;
    }

    protected function getStub(): string
    {
        return $this->resolveStubPath($this->wantsView() ? 'gmailable-view.stub' : 'gmailable.stub');
    }

    /**
     * Prefer a published stub in the app's stubs/ directory over the package's own.
     */
    protected function resolveStubPath(string $stub): string
    {
        return file_exists($custom = $this->laravel->basePath('stubs/'.$stub))
            ? $custom
            : dirname(__DIR__).'/stubs/'.$stub;
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Mail\Gmail';
    }
}
