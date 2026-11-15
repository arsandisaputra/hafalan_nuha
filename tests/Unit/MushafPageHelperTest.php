<?php

namespace Tests\Unit;

use App\Support\MushafPageHelper;
use PHPUnit\Framework\TestCase;

class MushafPageHelperTest extends TestCase
{
    public function test_calculates_juz_and_page_position_for_page_45(): void
    {
        $meta = MushafPageHelper::getPagePositionMetadata(45);

        $this->assertSame(3, $meta['juz_number']);
        $this->assertSame(4, $meta['page_in_juz']);
        $this->assertSame(20, $meta['total_pages_in_juz']);
        $this->assertSame('Halaman 45 / 604', $meta['page_label']);
        $this->assertSame('Halaman 4 dari 20 di Juz 3', $meta['juz_position_label']);
    }

    public function test_calculates_juz_boundaries_accurately(): void
    {
        $this->assertSame(1, MushafPageHelper::calculateJuzFromPage(1));
        $this->assertSame(1, MushafPageHelper::calculateJuzFromPage(21));
        $this->assertSame(2, MushafPageHelper::calculateJuzFromPage(22));
        $this->assertSame(2, MushafPageHelper::calculateJuzFromPage(41));
        $this->assertSame(30, MushafPageHelper::calculateJuzFromPage(582));
        $this->assertSame(30, MushafPageHelper::calculateJuzFromPage(604));
    }

    public function test_splits_arabic_verse_into_anchor_first_words_and_remaining(): void
    {
        $arabic = 'ذَٰلِكَ ٱلْكِتَـٰبُ لَا رَيْبَ فِيهِ هُدًى لِّلْمُتَّقِينَ';
        $split = MushafPageHelper::splitAnchorWords($arabic, 3);

        $this->assertSame('ذَٰلِكَ ٱلْكِتَـٰبُ لَا', $split['first_words']);
        $this->assertSame('رَيْبَ فِيهِ هُدًى لِّلْمُتَّقِينَ', $split['remaining_words']);
        $this->assertSame(7, $split['total_words']);
    }
}
