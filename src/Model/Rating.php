<?php
declare(strict_types=1);
namespace App\Model;
final class Rating
{
    public function __construct(private array $attributes) {}
    public static function fromArray(array $attributes): self { return new self($attributes); }
    public function toArray(): array { return $this->attributes; }
}
