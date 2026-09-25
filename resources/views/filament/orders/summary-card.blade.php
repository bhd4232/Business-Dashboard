{{--
    Order summary card (ViewOrder → "Order summary card"). Shows the card,
    then shares it as text (WhatsApp, Telegram links; Messenger and WeChat
    get the text copied first because they have no "send this text" link)
    or as a PNG image drawn on a canvas (phone share sheet / download).
--}}
<div
    x-data="{
        card: @js($card),
        notice: '',
        canShareFiles: false,
        init() {
            try {
                const probe = new File([new Blob(['x'], { type: 'image/png' })], 'probe.png', { type: 'image/png' });
                this.canShareFiles = !! (navigator.canShare && navigator.canShare({ files: [probe] }));
            } catch (e) {
                this.canShareFiles = false;
            }
        },
        isMobile() {
            return /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
        },
        async copyText(message) {
            try {
                await navigator.clipboard.writeText(this.card.text);
            } catch (e) {
                const area = document.createElement('textarea');
                area.value = this.card.text;
                document.body.appendChild(area);
                area.select();
                document.execCommand('copy');
                area.remove();
            }
            this.notice = message;
            setTimeout(() => this.notice = '', 5000);
        },
        async openAfterCopy(url) {
            await this.copyText(@js(__('Summary copied — paste it in the chat.')));
            window.location.href = url;
        },
        messenger() {
            this.openAfterCopy(this.isMobile() ? 'fb-messenger://' : 'https://www.messenger.com/');
        },
        wechat() {
            this.openAfterCopy('weixin://');
        },
        wrap(ctx, text, maxWidth) {
            const words = String(text).split(' ');
            const lines = [];
            let line = '';
            for (const word of words) {
                const test = line ? line + ' ' + word : word;
                if (ctx.measureText(test).width > maxWidth && line) {
                    lines.push(line);
                    line = word;
                } else {
                    line = test;
                }
            }
            if (line) lines.push(line);
            return lines;
        },
        draw() {
            const width = 1080, pad = 64, inner = width - pad * 2;
            const font = (size, weight = 400) => weight + ' ' + size + 'px system-ui, -apple-system, \'Segoe UI\', Roboto, \'Noto Sans Bengali\', sans-serif';
            const measure = document.createElement('canvas').getContext('2d');
            const ops = [];
            let y = pad;
            const text = (value, size, weight, color, x, align = 'left', maxWidth = inner) => {
                measure.font = font(size, weight);
                const lines = this.wrap(measure, value, maxWidth);
                lines.forEach((line, i) => ops.push({ t: 'text', value: line, size, weight, color, x, y: y + size + i * size * 1.35, align }));
                return lines.length * size * 1.35;
            };
            const rule = () => { ops.push({ t: 'rule', y: y }); y += 28; };

            y += text(this.card.company, 44, 700, '#0f172a', pad) + 6;
            y += text(this.card.title, 28, 500, '#475569', pad) + 24;
            rule();
            for (const row of this.card.meta) {
                measure.font = font(28, 600);
                const labelWidth = 230;
                ops.push({ t: 'text', value: row.label, size: 28, weight: 600, color: '#64748b', x: pad, y: y + 28, align: 'left' });
                y += text(row.value, 28, 500, '#0f172a', pad + labelWidth, 'left', inner - labelWidth) + 10;
            }
            if (this.card.items.length) {
                y += 8; rule();
                for (const item of this.card.items) {
                    const h = text(item.name + '  × ' + item.quantity, 28, 500, '#0f172a', pad, 'left', inner - 260);
                    ops.push({ t: 'text', value: item.amount, size: 28, weight: 600, color: '#0f172a', x: width - pad, y: y + 28, align: 'right' });
                    y += h + 10;
                }
            }
            y += 8; rule();
            for (const row of this.card.totals) {
                const size = row.strong ? 32 : 28, weight = row.strong ? 700 : 500;
                ops.push({ t: 'text', value: row.label, size, weight, color: '#334155', x: pad, y: y + size, align: 'left' });
                ops.push({ t: 'text', value: row.value, size, weight, color: '#0f172a', x: width - pad, y: y + size, align: 'right' });
                y += size * 1.35 + 8;
            }
            if (this.card.company_phone) {
                y += 16;
                y += text(this.card.company_phone, 24, 500, '#64748b', pad);
            }
            y += pad;

            const canvas = document.createElement('canvas');
            const scale = 1;
            canvas.width = width * scale;
            canvas.height = Math.ceil(y) * scale;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#f59e0b';
            ctx.fillRect(0, 0, canvas.width, 12);
            for (const op of ops) {
                if (op.t === 'rule') {
                    ctx.fillStyle = '#e2e8f0';
                    ctx.fillRect(pad, op.y, inner, 2);
                    continue;
                }
                ctx.font = font(op.size, op.weight);
                ctx.fillStyle = op.color;
                ctx.textAlign = op.align;
                ctx.fillText(op.value, op.x, op.y);
            }
            return canvas;
        },
        imageBlob() {
            return new Promise((resolve) => this.draw().toBlob(resolve, 'image/png'));
        },
        async shareImage() {
            const blob = await this.imageBlob();
            const file = new File([blob], this.card.file_name, { type: 'image/png' });
            try {
                await navigator.share({ files: [file], text: this.card.text });
            } catch (e) {
                if (e && e.name !== 'AbortError') this.downloadImage();
            }
        },
        async downloadImage() {
            const blob = await this.imageBlob();
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = this.card.file_name;
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 2000);
        },
    }"
    class="space-y-4"
>
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white text-gray-900 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-white">
        <div class="h-1.5 bg-amber-500"></div>
        <div class="space-y-4 p-4 sm:p-5">
            <div>
                <p class="text-lg font-bold" x-text="card.company"></p>
                <p class="text-sm text-gray-500 dark:text-gray-400" x-text="card.title"></p>
            </div>

            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 border-t border-gray-100 pt-3 text-sm dark:border-white/10">
                <template x-for="row in card.meta" :key="row.label">
                    <div class="contents">
                        <dt class="font-medium text-gray-500 dark:text-gray-400" x-text="row.label"></dt>
                        <dd class="break-words" x-text="row.value"></dd>
                    </div>
                </template>
            </dl>

            <ul class="space-y-1 border-t border-gray-100 pt-3 text-sm dark:border-white/10" x-show="card.items.length">
                <template x-for="(item, index) in card.items" :key="index">
                    <li class="flex justify-between gap-4">
                        <span><span x-text="item.name"></span> × <span x-text="item.quantity"></span></span>
                        <span class="whitespace-nowrap font-medium" x-text="item.amount"></span>
                    </li>
                </template>
            </ul>

            <dl class="space-y-1 border-t border-gray-100 pt-3 text-sm dark:border-white/10">
                <template x-for="row in card.totals" :key="row.label">
                    <div class="flex justify-between gap-4" :class="row.strong ? 'font-bold text-base' : ''">
                        <dt x-text="row.label"></dt>
                        <dd class="whitespace-nowrap" x-text="row.value"></dd>
                    </div>
                </template>
            </dl>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
        <a :href="card.whatsapp_url" target="_blank" rel="noopener"
           class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#25D366] px-3 py-2 text-sm font-semibold text-white hover:opacity-90">
            {{ __('WhatsApp') }}
        </a>
        <button type="button" x-on:click="wechat()"
           class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#07C160] px-3 py-2 text-sm font-semibold text-white hover:opacity-90">
            {{ __('WeChat') }}
        </button>
        <button type="button" x-on:click="messenger()"
           class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#0866FF] px-3 py-2 text-sm font-semibold text-white hover:opacity-90">
            {{ __('Messenger') }}
        </button>
        <a :href="card.telegram_url" target="_blank" rel="noopener"
           class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#229ED9] px-3 py-2 text-sm font-semibold text-white hover:opacity-90">
            {{ __('Telegram') }}
        </a>
    </div>

    <div class="flex flex-wrap gap-2">
        <button type="button" x-show="canShareFiles" x-on:click="shareImage()"
            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium hover:bg-gray-50 dark:border-white/15 dark:hover:bg-white/5">
            {{ __('Share as image') }}
        </button>
        <button type="button" x-on:click="copyText(@js(__('Summary copied.')))"
            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium hover:bg-gray-50 dark:border-white/15 dark:hover:bg-white/5">
            {{ __('Copy text') }}
        </button>
        <button type="button" x-on:click="downloadImage()"
            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium hover:bg-gray-50 dark:border-white/15 dark:hover:bg-white/5">
            {{ __('Download image') }}
        </button>
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        {{ __('Messenger and WeChat cannot receive text from a link, so the summary is copied first — paste it in the chat. To send the card as a picture, use Share as image or Download image.') }}
    </p>

    <p x-show="notice" x-transition class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300" x-text="notice"></p>
</div>
