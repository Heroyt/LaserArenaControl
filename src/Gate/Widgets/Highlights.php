<?php

namespace App\Gate\Widgets;

use App\GameModels\Game\Game;
use App\Models\DataObjects\Highlights\HighlightDto;
use App\Services\GameHighlight\GameHighlightService;
use DateTimeImmutable;
use DateTimeInterface;

class Highlights implements WidgetInterface
{

    /**
     * @var HighlightDto[]
     */
    private array $highlights;

    private string $hash;

    public function __construct(
      private readonly GameHighlightService $highlightService
    ) {}

    public function getData(?Game $game = null, ?DateTimeInterface $date = null, ?array $systems = []) : array {
        return [
          'highlights' => $this->getHighlights($date ?? new DateTimeImmutable()),
        ];
    }

    /**
     * @param  DateTimeInterface  $date
     * @return HighlightDto[]
     */
    private function getHighlights(DateTimeInterface $date) : array {
        if (!isset($this->highlights)) {
            $this->highlights = $this->highlightService->getHighlightsDataForDay($date);
        }
        return $this->highlights;
    }

    public function getTemplate() : string {
        return 'highlights.latte';
    }

    public function getSettingsTemplate() : string {
        return '';
    }

    public function getHash(?Game $game = null, ?DateTimeInterface $date = null, ?array $systems = []) : string {
        if (!isset($this->hash)) {
            $data = '';
            foreach ($this->getHighlights($date ?? new DateTimeImmutable()) as $highlight) {
                $data .= $highlight->code.$highlight->description;
            }
            $this->hash = md5($data);
        }
        return $this->hash;
    }
}