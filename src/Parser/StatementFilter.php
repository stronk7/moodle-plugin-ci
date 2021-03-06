<?php

/*
 * This file is part of the Moodle Plugin CI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace MoodlePluginCI\Parser;

use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Namespace_;

/**
 * Filter parsed code.
 */
class StatementFilter
{
    /**
     * @param array $statements
     *
     * @return Function_[]
     */
    public function filterFunctions(array $statements)
    {
        return array_filter($statements, function ($statement) {
            return $statement instanceof Function_;
        });
    }

    /**
     * Return class statements, does NOT return classes within namespaces!
     *
     * @param array $statements
     *
     * @return Class_[]
     */
    public function filterClasses(array $statements)
    {
        return array_filter($statements, function ($statement) {
            return $statement instanceof Class_;
        });
    }

    /**
     * Returns class names, including those within namespaces (one level deep).
     *
     * @param array $statements
     *
     * @return array
     */
    public function filterClassNames(array $statements)
    {
        $names = [];
        foreach ($this->filterClasses($statements) as $class) {
            $names[] = $class->name;
        }

        foreach ($this->filterNamespaces($statements) as $namespace) {
            foreach ($this->filterClasses($namespace->stmts) as $class) {
                $names[] = $namespace->name.'\\'.$class->name;
            }
        }

        return $names;
    }

    /**
     * @param array $statements
     *
     * @return Namespace_[]
     */
    public function filterNamespaces(array $statements)
    {
        return array_filter($statements, function ($statement) {
            return $statement instanceof Namespace_;
        });
    }

    /**
     * @param array $statements
     *
     * @return Assign[]
     */
    public function filterAssignments(array $statements)
    {
        $stmts = array_filter($statements, function ($statement) {
            // Since php-parser 4.0, expression statements are enclosed into
            // new Stmt\Expression node, confirm our Assignment is there.
            return $statement instanceof Expression && $statement->expr instanceof Assign;
        });
        // Return the Assignments only.
        return array_map(function ($statement) {
            return $statement->expr;
        }, $stmts);
    }

    /**
     * Find first variable assignment with a given name.
     *
     * @param array       $statements
     * @param string      $name
     * @param string|null $notFoundError
     *
     * @return Assign
     */
    public function findFirstVariableAssignment(array $statements, $name, $notFoundError = null)
    {
        foreach ($this->filterAssignments($statements) as $assign) {
            if ($assign->var instanceof Variable && (string) $assign->var->name === $name) {
                return $assign;
            }
        }

        throw new \RuntimeException($notFoundError ?: sprintf('Variable assignment $%s not found', $name));
    }

    /**
     * Find first property fetch assignment with a given name.
     *
     * EG: Find $foo->bar = something.
     *
     * @param array       $statements    PHP statements
     * @param string      $variable      The variable name, EG: foo in $foo->bar
     * @param string      $property      The property name, EG: bar in $foo->bar
     * @param string|null $notFoundError Use this error when not found
     *
     * @return Assign
     */
    public function findFirstPropertyFetchAssignment(array $statements, $variable, $property, $notFoundError = null)
    {
        foreach ($this->filterAssignments($statements) as $assign) {
            if (!$assign->var instanceof PropertyFetch) {
                continue;
            }
            if ((string) $assign->var->name !== $property) {
                continue;
            }
            $var = $assign->var->var;
            if ($var instanceof Variable && (string) $var->name === $variable) {
                return $assign;
            }
        }

        throw new \RuntimeException($notFoundError ?: sprintf('Variable assignment $%s->%s not found', $variable, $property));
    }

    /**
     * Given an array, find all the string keys.
     *
     * @param Array_ $array
     *
     * @return array
     */
    public function arrayStringKeys(Array_ $array)
    {
        $keys = [];
        foreach ($array->items as $item) {
            if (isset($item->key) && $item->key instanceof String_) {
                $keys[] = $item->key->value;
            }
        }

        return $keys;
    }
}
