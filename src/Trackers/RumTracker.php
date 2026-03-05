<?php

namespace AIHealth\Laravel\Trackers;

class RumTracker
{
    /**
     * Renders the Javascript snippet that natively tracks page speeds,
     * core web vitals, and CTA clicks. It uses keepalive fetch to send
     * the payload when the user navigates away securely.
     */
    public static function renderScript($key, $endpoint)
    {
        return <<<HTML
<!-- AI Store Health RUM Tracker -->
<script>
(function() {
    try {
        const _aihealth = {
            key: "{$key}",
            endpoint: "{$endpoint}",
            metrics: {
                load_time_ms: 0,
                js_time_ms: 0,
                vitals: { lcp: null, cls: null, fcp: null },
                cta_clicks: []
            },
            
            init() {
                this.observeVitals();
                this.observeLoad();
                this.observeClicks();
                this.scheduleSend();
            },
            
            observeVitals() {
                if (!window.PerformanceObserver) return;
                
                // LCP
                try {
                    new PerformanceObserver((entryList) => {
                        const entries = entryList.getEntries();
                        const lastEntry = entries[entries.length - 1];
                        this.metrics.vitals.lcp = Math.round(lastEntry.startTime);
                    }).observe({ type: 'largest-contentful-paint', buffered: true });
                } catch (e) {}
                
                // CLS
                try {
                    new PerformanceObserver((entryList) => {
                        for (const entry of entryList.getEntries()) {
                            if (!entry.hadRecentInput) {
                                this.metrics.vitals.cls = (this.metrics.vitals.cls || 0) + entry.value;
                            }
                        }
                    }).observe({ type: 'layout-shift', buffered: true });
                } catch (e) {}

                // FCP
                try {
                    new PerformanceObserver((entryList) => {
                        const entries = entryList.getEntriesByName('first-contentful-paint');
                        if (entries.length > 0) this.metrics.vitals.fcp = Math.round(entries[0].startTime);
                    }).observe({ type: 'paint', buffered: true });
                } catch (e) {}
            },
            
            observeLoad() {
                window.addEventListener('load', () => {
                    setTimeout(() => {
                        const timing = performance.timing;
                        if (timing && timing.loadEventEnd > 0) {
                            this.metrics.load_time_ms = Math.max(0, timing.loadEventEnd - timing.navigationStart);
                            this.metrics.js_time_ms = Math.max(0, timing.domInteractive - timing.responseEnd);
                        }
                    }, 0);
                });
            },
            
            observeClicks() {
                document.addEventListener('click', (e) => {
                    const el = e.target.closest('button, a, [role="button"], input[type="submit"]');
                    if (!el) return;
                    
                    const text = el.innerText || el.value || el.getAttribute('aria-label') || 'Unknown';
                    const tag = el.tagName.toLowerCase();
                    const href = el.getAttribute('href') || null;
                    const classes = el.getAttribute('class') || '';
                    
                    this.metrics.cta_clicks.push({
                        text: text.substring(0, 50).trim(),
                        tag: tag,
                        href: href,
                        classes: classes.substring(0, 100),
                        time: Date.now()
                    });
                }, { passive: true });
            },
            
            scheduleSend() {
                // Send payload when user leaves page or app goes to background
                const sendPayload = () => {
                    if (this.metrics.load_time_ms === 0) {
                        try {
                            const [navEntry] = performance.getEntriesByType("navigation");
                            if (navEntry) {
                                this.metrics.load_time_ms = Math.round(navEntry.loadEventEnd || navEntry.duration);
                                this.metrics.js_time_ms = Math.round(navEntry.domInteractive - navEntry.responseEnd);
                            }
                        } catch(e) {}
                    }
                    
                    const payload = {
                        url_path: window.location.href, // Central server sanitizes this
                        device_type: window.innerWidth < 768 ? 'mobile' : 'desktop',
                        load_time_ms: this.metrics.load_time_ms > 0 ? this.metrics.load_time_ms : null,
                        js_time_ms: this.metrics.js_time_ms > 0 ? this.metrics.js_time_ms : null,
                        vitals: this.metrics.vitals,
                        cta_clicks: this.metrics.cta_clicks
                    };

                    fetch(this.endpoint, {
                        method: 'POST',
                        body: JSON.stringify(payload),
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Private-Tracking-Key': this.key
                        },
                        keepalive: true // Crucial for visibilitychange unloading
                    }).catch(err => {
                        // Fail silently
                    });
                };
                
                window.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'hidden') {
                        sendPayload();
                        this.metrics.cta_clicks = []; // flush queue
                    }
                });
            }
        };
        
        _aihealth.init();
    } catch (e) {
        console.error('AIHealth RUM Error:', e);
    }
})();
</script>
HTML;
    }
}
