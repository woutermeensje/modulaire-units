<?php

namespace App\Admin;

/**
 * A form field of an admin resource. The type maps to resources/views/admin/fields/{type}.blade.php.
 */
class Field
{
    /** @var array<string|int, string> */
    public array $options = [];

    public ?string $help = null;

    public bool $fullWidth = true;

    public bool $required = false;

    public int $rows = 4;

    public ?string $placeholder = null;

    public ?string $slugSource = null;

    public function __construct(public string $name, public string $label, public string $type = 'text') {}

    public static function make(string $name, string $label, string $type = 'text'): self
    {
        return new self($name, $label, $type);
    }

    /**
     * @param  array<string|int, string>  $options
     */
    public function options(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function help(string $help): self
    {
        $this->help = $help;

        return $this;
    }

    public function half(): self
    {
        $this->fullWidth = false;

        return $this;
    }

    public function required(): self
    {
        $this->required = true;

        return $this;
    }

    public function rows(int $rows): self
    {
        $this->rows = $rows;

        return $this;
    }

    public function placeholder(string $placeholder): self
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function slugFrom(string $field): self
    {
        $this->slugSource = $field;

        return $this;
    }
}
