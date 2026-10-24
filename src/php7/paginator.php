<?php

/**
 * Exposes a paginator via $deps['paginator'], building JSON responses with
 * `data`, `meta` and `links` members plus `Link` and `X-Total-Count` headers.
 *
 * @see https://www.rfc-editor.org/rfc/rfc8288 (Web Linking)
 */
$deps['paginator'] = new class ($this) {
    /**
     * Framework instance, used to resolve other services.
     *
     * @var callable
     */
    private $mf;

    public function __construct(callable $mf)
    {
        $this->mf = $mf;
    }

    /**
     * Paginate a database table, page and size are read from the query string.
     *
     * Options:
     *   fields, where, order — passed to the database select()
     *   per_page             — default page size (default 20)
     *   max_per_page         — maximum page size requested by clients (default 100)
     *
     * @return object Response
     */
    public function paginate(string $table, array $options = [])
    {
        $options += ['fields' => '*', 'where' => null, 'order' => null];
        $database = call_user_func($this->mf, 'database');
        $page = $this->page($options);
        $rows = $database->select($table, 'COUNT(*) AS total', $options['where']);
        $total = (int) $rows[0]['total'];
        $items = $database->select(
            $table,
            $options['fields'],
            $options['where'],
            $options['order'],
            sprintf('%d OFFSET %d', $page['per_page'], ($page['page'] - 1) * $page['per_page'])
        );

        return $this->response($items, $total, $page);
    }

    /**
     * Paginate a list of items.
     *
     * @return object Response
     */
    public function paginateArray(array $items, array $options = [])
    {
        $page = $this->page($options);
        $slice = array_slice(array_values($items), ($page['page'] - 1) * $page['per_page'], $page['per_page']);

        return $this->response($slice, count($items), $page);
    }

    /**
     * Requested page and size.
     *
     * @return array Keys: page, per_page
     */
    private function page(array $options)
    {
        $options += ['per_page' => 20, 'max_per_page' => 100];
        $request = call_user_func($this->mf, 'request');
        $page = filter_var($request->query('page', 1), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $perPage = filter_var($request->query('per_page', $options['per_page']), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => $options['max_per_page']],
        ]);

        if (false === $page || false === $perPage) {
            throw call_user_func($this->mf, 'errors')->create(400, sprintf(
                'Query parameters "page" must be a positive integer and "per_page" between 1 and %d.',
                $options['max_per_page']
            ));
        }

        return ['page' => $page, 'per_page' => $perPage];
    }

    private function response(array $items, int $total, array $page)
    {
        $lastPage = max(1, (int) ceil($total / $page['per_page']));
        $links = [
            'first' => $this->url(1, $page['per_page']),
            'prev' => $page['page'] > 1 ? $this->url(min($page['page'] - 1, $lastPage), $page['per_page']) : null,
            'next' => $page['page'] < $lastPage ? $this->url($page['page'] + 1, $page['per_page']) : null,
            'last' => $this->url($lastPage, $page['per_page']),
        ];
        $header = [];

        foreach ($links as $rel => $url) {
            if (!is_null($url)) {
                $header[] = sprintf('<%s>; rel="%s"', $url, $rel);
            }
        }

        return call_user_func($this->mf, 'response')->json([
            'data' => $items,
            'meta' => [
                'page' => $page['page'],
                'per_page' => $page['per_page'],
                'total' => $total,
                'last_page' => $lastPage,
            ],
            'links' => $links,
        ], 200, [
            'Link' => implode(', ', $header),
            'X-Total-Count' => (string) $total,
        ]);
    }

    private function url(int $page, int $perPage)
    {
        $request = call_user_func($this->mf, 'request');
        $query = array_merge($request->query(), ['page' => $page, 'per_page' => $perPage]);

        return $request->path().'?'.http_build_query($query);
    }
};
