<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect\Query;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;

use const DIRECTORY_SEPARATOR;
use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;

use function array_any;
use function array_unique;
use function assert;
use function collect;
use function file_get_contents;
use function is_array;
use function is_dir;
use function is_string;
use function pathinfo;
use function preg_match;
use function preg_match_all;
use function scandir;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;

/**
 * Fluent query builder for view introspection.
 *
 * Allows querying Laravel Blade views with wildcard support and filtering by
 * relationships (extends, includes, uses).
 *
 * ```php
 * // Find all views matching a pattern
 * $views = Introspect::views()
 *     ->whereNameEquals('layouts.*')
 *     ->get();
 *
 * // Find views that extend a layout
 * $views = Introspect::views()
 *     ->whereExtends('layouts.app')
 *     ->get();
 *
 * // Find views used by another view
 * $views = Introspect::views()
 *     ->whereUsedBy('pages.home')
 *     ->get();
 *
 * // Complex OR queries
 * $views = Introspect::views()
 *     ->whereNameStartsWith('admin.')
 *     ->or(fn($q) => $q->whereNameStartsWith('dashboard.'))
 *     ->get();
 * ```
 * @author Brian Faust <brian@cline.sh>
 */
final class ViewsIntrospector
{
    /** @var array<string, string> */
    private array $filters = [];

    /** @var array<int, array<string, string>> */
    private array $orFilters = [];

    /**
     * Filter views by name pattern (supports wildcards).
     *
     * @param  string $pattern Pattern to match (e.g., 'layouts.*', '*admin*', 'pages.home')
     * @return static Fluent query builder instance
     */
    public function whereNameEquals(string $pattern): static
    {
        $this->filters['name'] = $pattern;

        return $this;
    }

    /**
     * Filter views by name prefix.
     *
     * @param  string $prefix Prefix to match (e.g., 'admin.', 'layouts.')
     * @return static Fluent query builder instance
     */
    public function whereNameStartsWith(string $prefix): static
    {
        $this->filters['starts_with'] = $prefix;

        return $this;
    }

    /**
     * Filter views by name suffix.
     *
     * @param  string $suffix Suffix to match (e.g., '.index', '.show')
     * @return static Fluent query builder instance
     */
    public function whereNameEndsWith(string $suffix): static
    {
        $this->filters['ends_with'] = $suffix;

        return $this;
    }

    /**
     * Filter views by name containing substring.
     *
     * @param  string $substring Substring to match
     * @return static Fluent query builder instance
     */
    public function whereNameContains(string $substring): static
    {
        $this->filters['contains'] = $substring;

        return $this;
    }

    /**
     * Filter views that are used by (included in) a specific view.
     *
     * Searches for @include, @includeIf, @includeWhen, @includeUnless, @each directives.
     *
     * @param  string $viewPattern Pattern of the parent view (supports wildcards)
     * @return static Fluent query builder instance
     */
    public function whereUsedBy(string $viewPattern): static
    {
        $this->filters['used_by'] = $viewPattern;

        return $this;
    }

    /**
     * Filter views that are NOT used by (not included in) a specific view.
     *
     * @param  string $viewPattern Pattern of the parent view (supports wildcards)
     * @return static Fluent query builder instance
     */
    public function whereNotUsedBy(string $viewPattern): static
    {
        $this->filters['not_used_by'] = $viewPattern;

        return $this;
    }

    /**
     * Filter views that use (include) a specific view.
     *
     * @param  string $viewName Name or pattern of the child view (supports wildcards)
     * @return static Fluent query builder instance
     */
    public function whereUses(string $viewName): static
    {
        $this->filters['uses'] = $viewName;

        return $this;
    }

    /**
     * Filter views that do NOT use (include) a specific view.
     *
     * @param  string $viewName Name or pattern of the child view (supports wildcards)
     * @return static Fluent query builder instance
     */
    public function whereDoesntUse(string $viewName): static
    {
        $this->filters['doesnt_use'] = $viewName;

        return $this;
    }

    /**
     * Filter views that extend a specific layout.
     *
     * Searches for @extends directive.
     *
     * @param  string $viewName Name or pattern of the layout (supports wildcards)
     * @return static Fluent query builder instance
     */
    public function whereExtends(string $viewName): static
    {
        $this->filters['extends'] = $viewName;

        return $this;
    }

    /**
     * Filter views that do NOT extend a specific layout.
     *
     * @param  string $viewName Name or pattern of the layout (supports wildcards)
     * @return static Fluent query builder instance
     */
    public function whereDoesntExtend(string $viewName): static
    {
        $this->filters['doesnt_extend'] = $viewName;

        return $this;
    }

    /**
     * Add OR logic to combine multiple filter conditions.
     *
     * ```php
     * $views = Introspect::views()
     *     ->whereNameStartsWith('admin.')
     *     ->or(fn($q) => $q->whereNameStartsWith('dashboard.'))
     *     ->get();
     * ```
     *
     * @param  callable $callback Callback that receives a new query builder instance
     * @return static   Fluent query builder instance
     */
    public function or(callable $callback): static
    {
        $orQuery = new self();
        $callback($orQuery);
        $this->orFilters[] = $orQuery->filters;

        return $this;
    }

    /**
     * Get all views matching the filters.
     *
     * @return Collection<int, string> Collection of view names
     */
    public function get(): Collection
    {
        $views = $this->getAllViews();

        return collect($views)->filter(fn (string $view): bool => $this->matchesFilters($view));
    }

    /**
     * Get the first matching view.
     *
     * @return null|string First matching view name or null
     */
    public function first(): ?string
    {
        return $this->get()->first();
    }

    /**
     * Check if any views match the filters.
     *
     * @return bool True if at least one view matches
     */
    public function exists(): bool
    {
        return $this->get()->isNotEmpty();
    }

    /**
     * Count matching views.
     *
     * @return int Number of matching views
     */
    public function count(): int
    {
        return $this->get()->count();
    }

    /**
     * Get all available views from Laravel's view finder.
     *
     * @return array<int, string> Array of view names
     */
    private function getAllViews(): array
    {
        $finder = View::getFinder();
        $views = [];

        // @phpstan-ignore method.notFound, foreach.nonIterable (FileViewFinder has these methods)
        foreach ($finder->getPaths() as $path) {
            assert(is_string($path));
            $views = [...$views, ...$this->findViewsInPath($path)];
        }

        /**
         * Also check namespaced views
         * @phpstan-ignore method.notFound (FileViewFinder has this method)
         */
        $hints = $finder->getHints();
        assert(is_array($hints));

        foreach ($hints as $namespace => $paths) {
            assert(is_string($namespace));
            assert(is_array($paths));

            foreach ($paths as $path) {
                assert(is_string($path));
                $namespacedViews = $this->findViewsInPath($path, $namespace);
                $views = [...$views, ...$namespacedViews];
            }
        }

        return array_unique($views);
    }

    /**
     * Recursively find all views in a directory path.
     *
     * @param  string             $path      Directory path to search
     * @param  null|string        $namespace Optional namespace prefix
     * @param  string             $prefix    Internal prefix for nested directories
     * @return array<int, string> Array of view names
     */
    private function findViewsInPath(string $path, ?string $namespace = null, string $prefix = ''): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $views = [];
        $files = scandir($path);

        foreach ($files as $file) {
            if ($file === '.') {
                continue;
            }

            if ($file === '..') {
                continue;
            }

            $fullPath = $path.DIRECTORY_SEPARATOR.$file;

            if (is_dir($fullPath)) {
                $nestedPrefix = $prefix !== '' && $prefix !== '0' ? sprintf('%s.%s', $prefix, $file) : $file;
                $views = [...$views, ...$this->findViewsInPath($fullPath, $namespace, $nestedPrefix)];
            } elseif (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $viewName = $prefix !== '' && $prefix !== '0'
                    ? $prefix.'.'.pathinfo($file, PATHINFO_FILENAME)
                    : pathinfo($file, PATHINFO_FILENAME);

                if ($namespace) {
                    $viewName = sprintf('%s::%s', $namespace, $viewName);
                }

                $views[] = $viewName;
            }
        }

        return $views;
    }

    /**
     * Check if a view matches all active filters.
     *
     * @param  string $view View name to check
     * @return bool   True if view matches all filters
     */
    private function matchesFilters(string $view): bool
    {
        // Check main filters (AND logic)
        $matchesMain = $this->matchesFilterSet($view, $this->filters);

        // If no OR filters, return main result
        if ($this->orFilters === []) {
            return $matchesMain;
        }

        // Check OR filters
        foreach ($this->orFilters as $orFilterSet) {
            if ($this->matchesFilterSet($view, $orFilterSet)) {
                return true;
            }
        }

        return $matchesMain;
    }

    /**
     * Check if a view matches a specific filter set.
     *
     * @param  string                $view      View name to check
     * @param  array<string, string> $filterSet Set of filters to match against
     * @return bool                  True if view matches all filters in the set
     */
    private function matchesFilterSet(string $view, array $filterSet): bool
    {
        if (isset($filterSet['name']) && !$this->matchesPattern($view, $filterSet['name'])) {
            return false;
        }

        if (isset($filterSet['starts_with']) && !str_starts_with($view, $filterSet['starts_with'])) {
            return false;
        }

        if (isset($filterSet['ends_with']) && !str_ends_with($view, $filterSet['ends_with'])) {
            return false;
        }

        if (isset($filterSet['contains']) && !str_contains($view, $filterSet['contains'])) {
            return false;
        }

        if (isset($filterSet['used_by']) && !$this->isUsedBy($view, $filterSet['used_by'])) {
            return false;
        }

        if (isset($filterSet['not_used_by']) && $this->isUsedBy($view, $filterSet['not_used_by'])) {
            return false;
        }

        if (isset($filterSet['uses']) && !$this->uses($view, $filterSet['uses'])) {
            return false;
        }

        if (isset($filterSet['doesnt_use']) && $this->uses($view, $filterSet['doesnt_use'])) {
            return false;
        }

        if (isset($filterSet['extends']) && !$this->extends($view, $filterSet['extends'])) {
            return false;
        }

        return !(isset($filterSet['doesnt_extend']) && $this->extends($view, $filterSet['doesnt_extend']));
    }

    /**
     * Check if a view is used by (included in) a parent view matching the pattern.
     *
     * @param  string $view        View name to check
     * @param  string $viewPattern Pattern of parent view (supports wildcards)
     * @return bool   True if view is used by matching parent
     */
    private function isUsedBy(string $view, string $viewPattern): bool
    {
        $allViews = $this->getAllViews();

        foreach ($allViews as $parentView) {
            if (!$this->matchesPattern($parentView, $viewPattern)) {
                continue;
            }

            if ($this->uses($parentView, $view)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a view uses (includes) another view matching the pattern.
     *
     * @param  string $view    View name to check
     * @param  string $pattern Pattern of child view (supports wildcards)
     * @return bool   True if view uses matching child view
     */
    private function uses(string $view, string $pattern): bool
    {
        $content = $this->getViewContent($view);

        if ($content === null) {
            return false;
        }

        // Find all @include, @includeIf, @includeWhen, @includeUnless, @each directives
        $includePattern = '/@(?:include(?:If|When|Unless|First)?|each)\s*\(\s*[\'"]([^\'"]+)[\'"]/';
        preg_match_all($includePattern, $content, $matches);

        return array_any($matches[1], fn (string $includedView): bool => $this->matchesPattern($includedView, $pattern));
    }

    /**
     * Check if a view extends a layout matching the pattern.
     *
     * @param  string $view    View name to check
     * @param  string $pattern Pattern of layout (supports wildcards)
     * @return bool   True if view extends matching layout
     */
    private function extends(string $view, string $pattern): bool
    {
        $content = $this->getViewContent($view);

        if ($content === null) {
            return false;
        }

        // Find @extends directive
        $extendsPattern = '/@extends\s*\(\s*[\'"]([^\'"]+)[\'"]/';
        preg_match($extendsPattern, $content, $matches);

        if (!isset($matches[1])) {
            return false;
        }

        return $this->matchesPattern($matches[1], $pattern);
    }

    /**
     * Get the content of a view file.
     *
     * @param  string      $view View name
     * @return null|string View content or null if not found
     */
    private function getViewContent(string $view): ?string
    {
        try {
            $finder = View::getFinder();
            $path = $finder->find($view);

            $content = file_get_contents($path);

            return $content !== false ? $content : null;
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Check if a value matches a wildcard pattern.
     *
     * @param  string $value   Value to check
     * @param  string $pattern Pattern with optional wildcards (*)
     * @return bool   True if value matches pattern
     */
    private function matchesPattern(string $value, string $pattern): bool
    {
        // Convert wildcard pattern to regex
        $regex = '/^'.str_replace(['\\', '*'], ['\\\\', '.*'], $pattern).'$/';

        return (bool) preg_match($regex, $value);
    }
}
