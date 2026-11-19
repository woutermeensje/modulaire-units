<?php

namespace Tests\Unit;

use App\Support\HeadingOutline;
use PHPUnit\Framework\TestCase;

class HeadingOutlineTest extends TestCase
{
    public function test_content_under_a_page_heading_gets_no_h1(): void
    {
        $html = HeadingOutline::normalize('<h1 class="x">Banner</h1><p>Tekst</p><h1>Tweede</h1>', hasPageHeading: true);

        $this->assertSame('<h2 class="x">Banner</h2><p>Tekst</p><h2>Tweede</h2>', $html);
    }

    public function test_only_the_first_h1_of_the_content_stays(): void
    {
        $html = HeadingOutline::normalize('<h1>Modulaire Unit Verkopen?</h1><p>Tekst</p><h1>Modulaire Units Verkopen</h1>', hasPageHeading: false);

        $this->assertSame('<h1>Modulaire Unit Verkopen?</h1><p>Tekst</p><h2>Modulaire Units Verkopen</h2>', $html);
    }

    public function test_the_first_h2_is_promoted_when_the_content_has_no_h1(): void
    {
        $html = HeadingOutline::normalize('<p>Intro</p><h2 class="t">Contact</h2><h2>Adres</h2>', hasPageHeading: false);

        $this->assertSame('<p>Intro</p><h1 class="t">Contact</h1><h2>Adres</h2>', $html);
    }

    public function test_nothing_is_promoted_when_the_first_heading_is_not_an_h2(): void
    {
        $html = '<h3>Stap 1</h3><h2>Later</h2>';

        $this->assertSame($html, HeadingOutline::normalize($html, hasPageHeading: false));
        $this->assertFalse(HeadingOutline::hasH1($html));
    }

    public function test_empty_headings_are_removed(): void
    {
        $html = HeadingOutline::normalize('<h1>Titel</h1><h1> &nbsp; </h1><h2><span></span></h2><p>Tekst</p>', hasPageHeading: false);

        $this->assertSame('<h1>Titel</h1><p>Tekst</p>', $html);
    }
}
