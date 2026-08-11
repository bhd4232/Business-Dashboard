@php
    $metaTracking = app(\App\Services\StorefrontMetaTrackingService::class);
    $metaProductionPage = ! isset($previewSlug);
    $metaEnabled = $metaProductionPage && $metaTracking->browserEnabled($setting, request());
    $metaPixelIds = $metaEnabled ? $metaTracking->browserPixelIds($setting) : [];
    $metaPageViewId = $metaEnabled && $metaTracking->browserEventEnabled($setting, 'PageView', request())
        ? $metaTracking->eventId('page-view')
        : null;
    $metaFlashedState = $metaEnabled ? session('storefront_meta_events', session('storefront_meta_event')) : null;
    $metaFlashedEvents = is_array($metaFlashedState) && array_is_list($metaFlashedState)
        ? $metaFlashedState
        : (is_array($metaFlashedState) ? [$metaFlashedState] : []);
    $metaCustomEvents = $metaEnabled ? $metaTracking->customEvents($setting) : [];
    $metaAdvancedMatching = [];
    $metaCustomer = auth('customer')->user();

    if (
        $metaEnabled
        && $setting->meta_advanced_matching_enabled
        && $metaCustomer instanceof \App\Models\Customer
        && $metaCustomer->company_id === $company->getKey()
    ) {
        $metaAdvancedMatching = $metaTracking->advancedMatchingData($metaCustomer);
    }
@endphp

@if ($metaProductionPage)
    @foreach ($metaTracking->domainVerificationTags($setting) as $verificationTag)
        <meta name="facebook-domain-verification" content="{{ $verificationTag }}">
    @endforeach
@endif

@if ($metaEnabled)
    <script>
        !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
        n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}
        (window,document,'script','https://connect.facebook.net/en_US/fbevents.js');

        @foreach ($metaPixelIds as $metaPixelId)
            fbq('init', {{ Illuminate\Support\Js::from($metaPixelId) }}@if ($metaAdvancedMatching !== []), {{ Illuminate\Support\Js::from($metaAdvancedMatching) }}@endif);
        @endforeach

        @if ($metaPageViewId)
            fbq('track', 'PageView', {}, {eventID: {{ Illuminate\Support\Js::from($metaPageViewId) }}});
        @endif

        @foreach ($metaFlashedEvents as $metaFlashedEvent)
            @if (is_array($metaFlashedEvent) && filled($metaFlashedEvent['name'] ?? null) && filled($metaFlashedEvent['event_id'] ?? null) && (($metaFlashedEvent['custom'] ?? false) || $metaTracking->browserEventEnabled($setting, $metaFlashedEvent['name'], request())))
                fbq(
                    {{ ($metaFlashedEvent['custom'] ?? false) ? "'trackCustom'" : "'track'" }},
                    {{ Illuminate\Support\Js::from($metaFlashedEvent['name']) }},
                    {{ Illuminate\Support\Js::from($metaFlashedEvent['data'] ?? []) }},
                    {eventID: {{ Illuminate\Support\Js::from($metaFlashedEvent['event_id']) }}}
                );
            @endif
        @endforeach

        @if ($metaCustomEvents !== [])
            (function () {
                var configuredEvents = {{ Illuminate\Support\Js::from($metaCustomEvents) }};
                var fired = new Set();
                var eventId = function (name) {
                    var id = window.crypto && typeof window.crypto.randomUUID === 'function'
                        ? window.crypto.randomUUID()
                        : Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
                    return 'custom-' + name.toLowerCase() + '-' + id;
                };
                var safeMatches = function (element, selector) {
                    try { return element && element.closest(selector); } catch (error) { return null; }
                };
                var safeQuery = function (selector) {
                    try { return document.querySelector(selector); } catch (error) { return null; }
                };
                var fire = function (event, key) {
                    if (key && fired.has(key)) { return; }
                    if (key) { fired.add(key); }
                    fbq('trackCustom', event.event_name, {configured_trigger: event.type}, {eventID: eventId(event.event_name)});
                };

                document.addEventListener('click', function (clickEvent) {
                    configuredEvents.forEach(function (event, index) {
                        if ((event.type === 'link' || event.type === 'click') && safeMatches(clickEvent.target, event.selector)) {
                            fire(event, event.type === 'link' ? null : 'click-' + index + '-' + clickEvent.timeStamp);
                        }
                    });
                });

                window.addEventListener('DOMContentLoaded', function () {
                    configuredEvents.forEach(function (event, index) {
                        var target = safeQuery(event.selector);
                        if (! target) { return; }

                        if (event.type === 'scroll' && 'IntersectionObserver' in window) {
                            var observer = new IntersectionObserver(function (entries) {
                                if (entries.some(function (entry) { return entry.isIntersecting; })) {
                                    fire(event, 'scroll-' + index);
                                    observer.disconnect();
                                }
                            }, {threshold: 0.35});
                            observer.observe(target);
                        }

                        if (event.type === 'time') {
                            window.setTimeout(function () { fire(event, 'time-' + index); }, Number(event.seconds || 1) * 1000);
                        }
                    });
                });
            })();
        @endif
    </script>

    @if (! $setting->meta_consent_required)
        @foreach ($metaPixelIds as $metaPixelId)
            <noscript><img height="1" width="1" class="hidden" alt="" src="https://www.facebook.com/tr?id={{ $metaPixelId }}&amp;ev=PageView&amp;noscript=1"></noscript>
        @endforeach
    @endif
@endif
