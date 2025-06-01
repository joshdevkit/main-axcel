<?php

namespace Axcel\Database\Traits;

trait WhereCompilers
{
    /**
     * Compile the "where" clause for the query.
     *
     * @param  array  $wheres
     * @return string
     */
    protected function compileWheres()
    {
        if (empty($this->wheres)) {
            return '';
        }

        $sql = ' WHERE ';
        $clauses = [];

        foreach ($this->wheres as $i => $where) {
            $boolean = $i === 0 ? '' : " {$where['boolean']} ";

            switch ($where['type']) {
                case 'basic':
                    $clauses[] = $boolean . "{$where['column']} {$where['operator']} ?";
                    break;
                case 'in':
                    $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                    $clauses[] = $boolean . "{$where['column']} IN ({$placeholders})";
                    break;

                case 'notIn':
                    $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                    $clauses[] = $boolean . "{$where['column']} NOT IN ({$placeholders})";
                    break;

                case 'null':
                    $clauses[] = $boolean . "{$where['column']} IS NULL";
                    break;
                case 'notNull':
                    $clauses[] = $boolean . "{$where['column']} IS NOT NULL";
                    break;
                case 'raw':
                    $clauses[] = $boolean . $where['sql'];
                    // Add raw bindings to where bindings
                    foreach ($where['bindings'] as $binding) {
                        $this->bindings['where'][] = $binding;
                    }
                    break;
            }
        }

        return $sql . implode('', $clauses);
    }

    /**
     * Get the SQL representation of the query.
     *
     * @return string
     */
    public function toSql()
    {
        $sql = "SELECT " . implode(', ', $this->columns) . " FROM {$this->table}";

        // Add joins if any
        if (!empty($this->joins)) {
            foreach ($this->joins as $join) {
                $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
            }
        }

        // Add where clauses if any
        if (!empty($this->wheres)) {
            $sql .= $this->compileWheres();
        }

        // Add group by if any
        if (!empty($this->groups)) {
            $sql .= " GROUP BY " . implode(', ', $this->groups);
        }

        // Add having if any
        if (!empty($this->havings)) {
            $sql .= " HAVING ";

            foreach ($this->havings as $i => $having) {
                if ($i > 0) {
                    $sql .= " {$having['boolean']} ";
                }

                $sql .= "{$having['column']} {$having['operator']} ?";
            }
        }

        // Add order by if any
        if (!empty($this->orders)) {
            $sql .= " ORDER BY ";

            foreach ($this->orders as $i => $order) {
                if ($i > 0) {
                    $sql .= ", ";
                }

                $sql .= "{$order['column']} {$order['direction']}";
            }
        }

        // Add limit and offset if any
        if (!is_null($this->limit)) {
            $sql .= " LIMIT {$this->limit}";
        }

        if (!is_null($this->offset)) {
            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }
}
