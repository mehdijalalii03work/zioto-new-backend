<?php

namespace Modules\Product\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Product\Models\ProductCategory;

class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    public function definition(): array
    {
        return [
            'name' => $name = fake()->unique()->words(2, true),
            'slug' => str()->slug($name),
            'description' => fake()->optional()->sentence(),
            'icon' => fake()->optional()->randomElement([
                'heroicon-o-tag',
                'heroicon-o-star',
                'heroicon-o-heart',
                'heroicon-o-sparkles',
            ]),
            'color' => fake()->optional()->randomElement([
                'primary', 'success', 'warning', 'danger', 'info',
            ]),
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
            'seo_title' => fake()->optional()->words(4, true),
            'seo_description' => fake()->optional()->sentence(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function childOf(ProductCategory $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
        ]);
    }
}
