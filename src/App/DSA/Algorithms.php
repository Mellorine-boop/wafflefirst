<?php
// Five algorithms DSA 
namespace App\DSA;

class Algorithms {
    // Algorithm #1: Insertion Sort. Good for small lists that are nearly sorted.
    public static function insertionSort(array $items, callable $compare): array {
        for ($i = 1; $i < count($items); $i++) {
            $current = $items[$i];
            $j = $i - 1;
            while ($j >= 0 && $compare($items[$j], $current) > 0) {
                $items[$j + 1] = $items[$j];
                $j--;
            }
            $items[$j + 1] = $current;
        }
        return $items;
    }

    // Algorithm #2: Binary Search. Requires the input list to already be sorted.
    public static function binarySearch(array $items, mixed $target, callable $compare): mixed {
        $low = 0;
        $high = count($items) - 1;
        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            $result = $compare($items[$mid], $target);
            if ($result === 0) return $items[$mid];
            if ($result < 0) $low = $mid + 1;
            else $high = $mid - 1;
        }
        return null;
    }

    // Algorithm #3: Linear Search. Checks every item until a match is found.
    public static function linearSearchAll(array $items, callable $predicate): array {
        $found = [];
        foreach ($items as $item) {
            if ($predicate($item)) $found[] = $item;
        }
        return $found;
    }

    // Algorithm #4: Bubble Sort. Repeatedly swaps adjacent out-of-order values.
    public static function bubbleSort(array $items, callable $compare): array {
        $n = count($items);
        for ($i = 0; $i < $n - 1; $i++) {
            $swapped = false;
            for ($j = 0; $j < $n - $i - 1; $j++) {
                if ($compare($items[$j], $items[$j + 1]) > 0) {
                    [$items[$j], $items[$j + 1]] = [$items[$j + 1], $items[$j]];
                    $swapped = true;
                }
            }
            if (!$swapped) break;
        }
        return $items;
    }

    // Algorithm #5: Selection Sort. Finds the smallest remaining item and places it next.
    public static function selectionSort(array $items, callable $compare): array {
        $n = count($items);
        for ($i = 0; $i < $n - 1; $i++) {
            $min = $i;
            for ($j = $i + 1; $j < $n; $j++) {
                if ($compare($items[$j], $items[$min]) < 0) $min = $j;
            }
            if ($min !== $i) [$items[$i], $items[$min]] = [$items[$min], $items[$i]];
        }
        return $items;
    }
}
