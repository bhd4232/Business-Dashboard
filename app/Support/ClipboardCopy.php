<?php

namespace App\Support;

use Illuminate\Support\Js;

/**
 * The exact Alpine snippet Filament's own ->copyable() columns/entries use
 * (see vendor/filament/tables/src/Columns/TextColumn.php), extracted so a
 * plain Filament\Actions\Action -- which has no built-in ->copyable() --
 * can offer the same "click to copy, toast confirms" behaviour. Used by
 * DeploymentErrorAlert's notification action and DeploymentErrorResource's
 * row action, both copying a full error log the owner can hand to an agent.
 */
class ClipboardCopy
{
    public static function alpineHandler(string $text, string $message = 'Copied to clipboard'): string
    {
        $textJs = Js::from($text);
        $messageJs = Js::from($message);

        return <<<JS
            window.navigator.clipboard.writeText({$textJs})
            \$tooltip({$messageJs}, {
                theme: \$store.theme,
                timeout: 1500,
            })
            JS;
    }
}
