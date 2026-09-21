<?php

declare(strict_types=1);

namespace Jfcherng\Diff\Renderer\Html\LineRenderer;

use Jfcherng\Utility\MbString;

interface LineRendererInterface
{
    /**
     * Renderer the in-line changed extent.
     *
     * @param MbString $mbOld the old megabytes line
     * @param MbString $mbNew the new megabytes line
     *
     * @return static
     */
    public function render(MbString $mbOld, MbString $mbNew): self;
}
