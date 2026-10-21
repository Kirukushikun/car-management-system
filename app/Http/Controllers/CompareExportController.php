<?php

namespace App\Http\Controllers;

use App\Services\PeriodComparison;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The period comparison as a CSV: the same choices as the page (from its query string) and the
 * same figures, one row per line of the comparison.
 */
class CompareExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $today = CarbonImmutable::today();
        $rightYear = (int) $request->query('rightYear', (string) $today->year);
        $years = [
            'left' => $request->boolean('linkYears', true) ? $rightYear - 1 : (int) $request->query('leftYear', (string) ($today->year - 1)),
            'right' => $rightYear,
        ];
        $side = fn (string $prefix): array => [
            'year' => $years[$prefix],
            'period' => array_key_exists((string) $request->query("{$prefix}Period"), PeriodComparison::PERIODS) ? (string) $request->query("{$prefix}Period") : 'year',
            'from' => $request->query("{$prefix}From"),
            'to' => $request->query("{$prefix}To"),
            'line' => $request->integer("{$prefix}Line") ?: null,
            'farm' => $request->integer("{$prefix}Farm") ?: null,
        ];

        $comparison = (new PeriodComparison(
            left: $side('left'),
            right: $side('right'),
            sameFilters: $request->boolean('sameFilters', true),
            sameSpan: $request->boolean('sameSpan', true),
        ))->build();

        $left = $comparison['left'];
        $right = $comparison['right'];
        $filename = 'car-comparison-'.str($left['label'].'-vs-'.$right['label'])->slug().'.csv';

        return response()->streamDownload(function () use ($comparison, $left, $right): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Section', 'Item', $left['label'], $right['label'], 'Change']);
            fputcsv($out, ['Period', 'Dates', $left['range'], $right['range'], '']);
            fputcsv($out, ['Period', 'Filters', $left['filters'], $right['filters'], '']);

            foreach ($comparison['rows'] as $row) {
                fputcsv($out, ['Headline', $row['label'], $row['left_text'], $row['right_text'], $row['change'] ?? '']);
            }

            foreach ($comparison['months'] as $pair) {
                fputcsv($out, ['Per month', ($pair['left']['label'] ?? '—').' / '.($pair['right']['label'] ?? '—'), $pair['left']['count'] ?? '', $pair['right']['count'] ?? '', '']);
            }

            foreach ($comparison['breakdowns'] as $breakdown) {
                foreach ($breakdown['rows'] as $row) {
                    fputcsv($out, [$breakdown['title'], $row['name'], $row['left'], $row['right'], $row['change'] ?? '']);
                }
            }

            foreach ($comparison['highlights'] as $highlight) {
                fputcsv($out, ['Highlight', $highlight, '', '', '']);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
