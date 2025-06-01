<?php

namespace Axcel\Database;

use Axcel\Support\Collection;
use IteratorAggregate;
use Countable;

class Paginator implements IteratorAggregate, Countable
{
    /**
     * The collection of items.
     *
     * @var \Axcel\Support\Collection|\Axcel\Database\Collection
     */
    protected $items;

    /**
     * The total number of items.
     *
     * @var int
     */
    protected $total;

    /**
     * The number of items per page.
     *
     * @var int
     */
    protected $perPage;

    /**
     * The current page number.
     *
     * @var int
     */
    protected $currentPage;

    /**
     * The last page number.
     *
     * @var int
     */
    protected $lastPage;

    /**
     * The starting item number.
     *
     * @var int
     */
    protected $from;

    /**
     * The ending item number.
     *
     * @var int
     */
    protected $to;

    /**
     * Create a new paginator instance.
     *
     * @param  \Axcel\Support\Collection|\Axcel\Database\Collection  $items
     * @param  int  $total
     * @param  int  $perPage
     * @param  int  $currentPage
     * @return void
     */
    public function __construct($items, $total, $perPage, $currentPage)
    {
        $this->items = $items;
        $this->total = $total;
        $this->perPage = $perPage;
        $this->currentPage = $currentPage;
        $this->lastPage = ceil($total / $perPage);
        $this->from = ($currentPage - 1) * $perPage + 1;
        $this->to = min($currentPage * $perPage, $total);
    }

    /**
     * Get the collection of items.
     *
     * @return \Axcel\Support\Collection|\Axcel\Database\Collection
     */
    public function items()
    {
        return $this->items;
    }

    /**
     * Get the total number of items.
     *
     * @return int
     */
    public function total()
    {
        return $this->total;
    }

    /**
     * Get the number of items per page.
     *
     * @return int
     */
    public function perPage()
    {
        return $this->perPage;
    }

    /**
     * Get the current page number.
     *
     * @return int
     */
    public function currentPage()
    {
        return $this->currentPage;
    }

    /**
     * Get the last page number.
     *
     * @return int
     */
    public function lastPage()
    {
        return $this->lastPage;
    }

    /**
     * Get the starting item number.
     *
     * @return int
     */
    public function from()
    {
        return $this->from;
    }

    /**
     * Get the ending item number.
     *
     * @return int
     */
    public function to()
    {
        return $this->to;
    }

    /**
     * Determine if there are more items in the data source.
     *
     * @return bool
     */
    public function hasMorePages()
    {
        return $this->currentPage < $this->lastPage;
    }

    /**
     * Get the URL for the previous page.
     *
     * @return string|null
     */
    public function previousPageUrl()
    {
        if ($this->currentPage > 1) {
            return $this->url($this->currentPage - 1);
        }
        return null;
    }

    /**
     * Get the URL for the next page.
     *
     * @return string|null
     */
    public function nextPageUrl()
    {
        if ($this->hasMorePages()) {
            return $this->url($this->currentPage + 1);
        }
        return null;
    }

    /**
     * Get the URL for a given page number.
     *
     * @param  int  $page
     * @return string
     */
    public function url($page)
    {
        // Parse current URL
        $url = parse_url($_SERVER['REQUEST_URI']);
        $path = $url['path'] ?? '';

        // Parse existing query parameters
        $queryParams = [];
        if (isset($url['query'])) {
            parse_str($url['query'], $queryParams);
        }

        // Set the page parameter
        $queryParams['page'] = $page;

        // Build the new URL
        $queryString = http_build_query($queryParams);
        return $path . ($queryString ? '?' . $queryString : '');
    }

    /**
     * Generate pagination links HTML.
     *
     * @return string
     */
    public function links()
    {
        $html = '<nav><ul class="pagination">';

        // Previous page link
        if ($this->currentPage > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $this->previousPageUrl() . '">&laquo;</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
        }

        // Page numbers
        for ($i = 1; $i <= $this->lastPage; $i++) {
            if ($i == $this->currentPage) {
                $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
            } else {
                $html .= '<li class="page-item"><a class="page-link" href="' . $this->url($i) . '">' . $i . '</a></li>';
            }
        }

        // Next page link
        if ($this->hasMorePages()) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $this->nextPageUrl() . '">&raquo;</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
        }

        $html .= '</ul></nav>';
        return $html;
    }

    // IteratorAggregate interface method
    public function getIterator(): \Traversable
    {
        return $this->items->getIterator();
    }

    // Countable interface
    public function count(): int
    {
        return $this->items->count();
    }

    /**
     * Convert the paginator instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'data' => method_exists($this->items, 'toArray') ? $this->items->toArray() : $this->items,
            'total' => $this->total,
            'per_page' => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page' => $this->lastPage,
            'from' => $this->from,
            'to' => $this->to,
        ];
    }
}
