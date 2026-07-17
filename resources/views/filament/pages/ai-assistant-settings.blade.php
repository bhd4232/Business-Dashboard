<x-filament-panels::page>
    <x-filament::section heading="AI Auto-Reply" description="Grounded-only assistant: it only answers with prices, stock, and FAQs looked up live from your own data, and hands anything uncertain to a human.">
        <div style="display: grid; gap: 1rem; max-width: 640px;">
            <label style="display: flex; align-items: center; gap: .6rem; font-size: .875rem; font-weight: 600;">
                <input type="checkbox" wire:model="settings.enabled" style="width: 1.1rem; height: 1.1rem;">
                Enable AI auto-reply for this company
            </label>

            <div>
                <label style="display:block; font-size: .8rem; font-weight: 600; margin-bottom: .3rem;">LLM Provider</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model="settings.provider">
                        <option value="anthropic">Anthropic (Claude)</option>
                        <option value="openai">OpenAI</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <div>
                <label style="display:block; font-size: .8rem; font-weight: 600; margin-bottom: .3rem;">Model</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="settings.model" placeholder="claude-haiku-4-5-20251001" />
                </x-filament::input.wrapper>
            </div>

            <div>
                <label style="display:block; font-size: .8rem; font-weight: 600; margin-bottom: .3rem;">
                    API Key {{ ($settings['has_api_key'] ?? false) ? '(already saved — leave blank to keep it)' : '' }}
                </label>
                <x-filament::input.wrapper>
                    <x-filament::input type="password" wire:model="settings.api_key" placeholder="{{ ($settings['has_api_key'] ?? false) ? '••••••••' : 'sk-...' }}" />
                </x-filament::input.wrapper>
                <p style="font-size: .72rem; color: rgb(113 113 122); margin-top: .3rem;">Stored encrypted per company.</p>
            </div>

            <div>
                <label style="display:block; font-size: .8rem; font-weight: 600; margin-bottom: .3rem;">Confidence threshold (0–1)</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="number" step="0.05" min="0" max="1" wire:model="settings.confidence_threshold" />
                </x-filament::input.wrapper>
                <p style="font-size: .72rem; color: rgb(113 113 122); margin-top: .3rem;">Replies below this confidence are held for a human instead of being sent.</p>
            </div>

            <div>
                <label style="display:block; font-size: .8rem; font-weight: 600; margin-bottom: .3rem;">Max consecutive AI replies</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="number" min="1" max="20" wire:model="settings.max_consecutive_ai_replies" />
                </x-filament::input.wrapper>
            </div>

            <div>
                <label style="display:block; font-size: .8rem; font-weight: 600; margin-bottom: .3rem;">Brand voice (optional)</label>
                <x-filament::input.wrapper>
                    <textarea wire:model="settings.brand_voice" rows="3"
                        placeholder="e.g. friendly, uses simple Bengali, addresses customers as 'আপনি'"
                        style="width: 100%; border: none; background: transparent; outline: none; padding: .5rem .75rem; font-size: .875rem; color: inherit; resize: vertical;"></textarea>
                </x-filament::input.wrapper>
            </div>

            <div>
                <x-filament::button wire:click="save" icon="heroicon-m-check">Save settings</x-filament::button>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
