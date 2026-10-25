<?php

/**
 * Pagination of database rows, the page is read from the `page` query string value.
 *
 * Results are arrays with the rows (`data`), the counts (`meta`) and the URLs
 * of the other pages (`links`), sent as JSON with the `Link` header by `response()`.
 *
 * @link https://www.rfc-editor.org/rfc/rfc8288 (Link header)
 */
$deps['paginator'] = new class ($deps) {
    /**
     * Dynamic set of dependencies, used to retrieve the database, request and response services.
     *
     * @var array
     */
    private $deps;

    /**
     * @param array $deps Dynamic set of dependencies.
     */
    public function __construct(array &$deps = [])
    {
        $this->deps = &$deps;
    }

    /**
     * Rows of the current page, the conditions and the order are the ones of `database->select()`.
     *
     * @param string                    $table
     * @param array|integer|string|null $where
     * @param string|null               $order   Use a unique order, so rows don't move between pages
     * @param int                       $perPage Rows of each page
     * @param array|string              $fields
     *
     * @throws InvalidArgumentException If rows of each page are less than 1
     */
    public function paginate(string $table, $where = null, $order = null, int $perPage = 15, $fields = '*'): array
    {
        if ($perPage < 1) {
            throw new InvalidArgumentException('Rows of each page must be at least 1.');
        }

        $database = $this->service('database');
        $page = $this->page();
        $total = (int) $database->select($table, 'COUNT(*) AS total', $where)[0]['total'];
        $pages = max(1, (int) ceil($total / $perPage));

        // pages after the last one are empty
        $data = $page > $pages ? [] : $database->select($table, $fields, $where, $order, sprintf('%d OFFSET %d', $perPage, ($page - 1) * $perPage));

        return [
            'data' => $data,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'pages' => $pages,
            ],
            'links' => [
                'first' => $this->url(1),
                'prev' => $page > 1 ? $this->url(min($page - 1, $pages)) : null,
                'next' => $page < $pages ? $this->url($page + 1) : null,
                'last' => $this->url($pages),
            ],
        ];
    }

    /**
     * JSON response of a page, with the `Link` header.
     */
    public function response(array $result)
    {
        $links = [];

        foreach ($result['links'] ?? [] as $rel => $url) {
            if (!is_null($url)) {
                $links[] = sprintf('<%s>; rel="%s"', $url, $rel);
            }
        }

        $response = $this->service('response')->json($result);

        return empty($links) ? $response : $response->setHeader('Link', implode(', ', $links));
    }

    /**
     * Current page, 1 when missing or invalid.
     */
    public function page(): int
    {
        $page = $this->service('request')->query('page');

        return is_string($page) ? (int) filter_var($page, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'default' => 1]]) : 1;
    }

    /**
     * URL of a page, the other query string values are kept.
     */
    public function url(int $page): string
    {
        $request = $this->service('request');
        $query = array_replace($request->query(), ['page' => $page]);

        return $request->path().'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Retrieve a service, it can be replaced at runtime.
     *
     * @return object
     */
    private function service(string $name)
    {
        $service = $this->deps[$name];

        return is_callable($service)
            ? call_user_func($service)
            : $service;
    }
};
