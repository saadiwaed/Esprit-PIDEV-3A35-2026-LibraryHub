<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Process\Process;

final class PerformanceService
{
    /** @var array<string, array{start_time_ns: int, start_memory: int, start_peak_memory: int}> */
    private array $markers = [];

    public function start(string $name): void
    {
        $this->markers[$name] = [
            'start_time_ns' => (int) hrtime(true),
            'start_memory' => memory_get_usage(false),
            'start_peak_memory' => memory_get_peak_usage(false),
        ];
    }

    /**
     * @return array{duration_ms: float, memory_delta_bytes: int, peak_memory_bytes: int}
     */
    public function stop(string $name): array
    {
        if (!isset($this->markers[$name])) {
            return [
                'duration_ms' => 0.0,
                'memory_delta_bytes' => 0,
                'peak_memory_bytes' => memory_get_peak_usage(false),
            ];
        }

        $start = $this->markers[$name];
        unset($this->markers[$name]);

        $durationMs = (hrtime(true) - $start['start_time_ns']) / 1_000_000;
        $memoryDelta = memory_get_usage(false) - $start['start_memory'];
        $peakMemoryDelta = memory_get_peak_usage(false) - $start['start_peak_memory'];

        return [
            'duration_ms' => $durationMs,
            'memory_delta_bytes' => $memoryDelta,
            'peak_memory_bytes' => $peakMemoryDelta,
        ];
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return array{result: T, metrics: array{duration_ms: float, memory_delta_bytes: int, peak_memory_bytes: int}}
     */
    public function measure(string $name, callable $callback): array
    {
        $this->start($name);
        $result = $callback();

        return [
            'result' => $result,
            'metrics' => $this->stop($name),
        ];
    }

    /**
     * @param callable(): void $callback
     * @return array{
     *   runs: int,
     *   avg_duration_ms: float,
     *   min_duration_ms: float,
     *   max_duration_ms: float,
     *   avg_memory_delta_bytes: float,
     *   avg_peak_memory_bytes: float
     * }
     */
    public function measureAverage(string $name, int $runs, callable $callback): array
    {
        $durations = [];
        $memoryDeltas = [];
        $peakDeltas = [];

        for ($i = 1; $i <= $runs; $i++) {
            $measured = $this->measure($name.'_'.$i, static function () use ($callback): void {
                $callback();
            });
            $metrics = $measured['metrics'];

            $durations[] = $metrics['duration_ms'];
            $memoryDeltas[] = $metrics['memory_delta_bytes'];
            $peakDeltas[] = $metrics['peak_memory_bytes'];
        }

        return [
            'runs' => $runs,
            'avg_duration_ms' => $this->average($durations),
            'min_duration_ms' => $this->minimum($durations),
            'max_duration_ms' => $this->maximum($durations),
            'avg_memory_delta_bytes' => $this->average($memoryDeltas),
            'avg_peak_memory_bytes' => $this->average($peakDeltas),
        ];
    }

    /**
     * @param list<string> $command
     * @return array{
     *   command: string,
     *   success: bool,
     *   runs: int,
     *   avg_duration_ms: float,
     *   min_duration_ms: float,
     *   max_duration_ms: float,
     *   last_error: string|null
     * }
     */
    public function measureCommandAverage(string $name, array $command, int $runs, string $workingDir): array
    {
        $durations = [];
        $lastError = null;
        $executedRuns = 0;

        for ($i = 1; $i <= $runs; $i++) {
            $metrics = $this->measure($name.'_'.$i, static function () use ($command, $workingDir, &$lastError): void {
                $process = new Process($command, $workingDir, null, null, 300);
                $process->run();

                if (!$process->isSuccessful()) {
                    $errorOutput = trim($process->getErrorOutput());
                    $stdOutput = trim($process->getOutput());
                    $lastError = $errorOutput !== '' ? $errorOutput : $stdOutput;
                }
            })['metrics'];

            $durations[] = $metrics['duration_ms'];
            $executedRuns++;

            if ($lastError !== null) {
                break;
            }
        }

        return [
            'command' => implode(' ', $command),
            'success' => $lastError === null,
            'runs' => $executedRuns,
            'avg_duration_ms' => $this->average($durations),
            'min_duration_ms' => $this->minimum($durations),
            'max_duration_ms' => $this->maximum($durations),
            'last_error' => $lastError,
        ];
    }

    /**
     * @param list<int|float> $values
     */
    private function average(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        return array_sum($values) / count($values);
    }

    /**
     * @param list<int|float> $values
     */
    private function minimum(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        return (float) min($values);
    }

    /**
     * @param list<int|float> $values
     */
    private function maximum(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        return (float) max($values);
    }
}
