<?php

namespace App\Reservation;

final class SeatMapBuilder
{
    private const SEATS_PER_ROW = 12;

    /**
     * @return list<string>
     */
    public function buildSeatLabels(int $seatsTotal): array
    {
        if ($seatsTotal < 1) {
            return [];
        }

        $labels = [];

        for ($index = 0; $index < $seatsTotal; ++$index) {
            $rowIndex = intdiv($index, self::SEATS_PER_ROW);
            $seatNumber = ($index % self::SEATS_PER_ROW) + 1;
            $labels[] = sprintf('%s-%02d', $this->buildRowLabel($rowIndex), $seatNumber);
        }

        return $labels;
    }

    /**
     * @param list<string> $reservedSeatLabels
     * @return array{layout: array{columns: int, rows: int}, items: list<array{label: string, status: string}>}
     */
    public function buildSeatMap(int $seatsTotal, array $reservedSeatLabels): array
    {
        $seatLabels = $this->buildSeatLabels($seatsTotal);
        $reservedSet = [];

        foreach ($reservedSeatLabels as $seatLabel) {
            $normalized = strtoupper(trim($seatLabel));
            if ('' !== $normalized) {
                $reservedSet[$normalized] = true;
            }
        }

        $items = array_map(
            static fn (string $label): array => [
                'label' => $label,
                'status' => isset($reservedSet[$label]) ? 'reserved' : 'available',
            ],
            $seatLabels,
        );

        return [
            'layout' => [
                'columns' => self::SEATS_PER_ROW,
                'rows' => (int) ceil(count($seatLabels) / self::SEATS_PER_ROW),
            ],
            'items' => $items,
        ];
    }

    private function buildRowLabel(int $index): string
    {
        $label = '';
        $value = $index;

        do {
            $remainder = $value % 26;
            $label = chr(65 + $remainder).$label;
            $value = intdiv($value, 26) - 1;
        } while ($value >= 0);

        return $label;
    }
}
