<?php

namespace App\Services;

/**
 * Deterministic task suggestions from the data snapshot.
 * Each suggestion: category{fa,en}, title{fa,en}, description{fa,en}, priority, reason, evidence, rule_id.
 */
class RulesEngine
{
    public const CATEGORIES = [
        'setup' => ['fa' => 'راه‌اندازی و اتصال‌ها', 'en' => 'Setup & connections'],
        'seo' => ['fa' => 'سئو', 'en' => 'SEO'],
        'ux' => ['fa' => 'تجربه کاربری', 'en' => 'User experience'],
        'conversion' => ['fa' => 'بهبود فروش و تبدیل', 'en' => 'Sales & conversion'],
        'tech' => ['fa' => 'فنی', 'en' => 'Technical'],
    ];

    /** @return array<int, array> */
    public function run(array $data): array
    {
        $out = [];
        foreach ($this->rules() as $id => $rule) {
            try {
                foreach ($rule($data) as $s) {
                    $s['rule_id'] = $id;
                    $s['source'] = 'rule';
                    $s['priority'] ??= 'medium';
                    $out[] = $s;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $out;
    }

    protected function s(array $cat, string $tfa, string $ten, string $dfa, string $den, string $reason, $evidence = null, string $priority = 'medium'): array
    {
        return [
            'category' => $cat, 'title' => ['fa' => $tfa, 'en' => $ten], 'description' => ['fa' => $dfa, 'en' => $den],
            'reason' => $reason, 'evidence' => $evidence, 'priority' => $priority,
        ];
    }

    protected function short(?string $s, int $n = 60): string
    {
        $s = (string) $s;

        return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1).'…' : $s;
    }

    /** @return array<string, callable(array): array> */
    protected function rules(): array
    {
        $C = self::CATEGORIES;

        return [
            'site-no-https' => fn ($d) => (($d['site']['status'] ?? 0) && empty($d['site']['https'])) ? [$this->s($C['tech'],
                'فعال‌سازی HTTPS روی سایت', 'Enable HTTPS on the site',
                'سایت روی HTTP باز می‌شود. گواهی SSL (مثلاً رایگان Let\'s Encrypt) نصب کن و همه صفحات را به HTTPS هدایت کن. گوگل و مرورگرها سایت بدون HTTPS را ناامن نشان می‌دهند.',
                'The site loads over HTTP. Install an SSL certificate (e.g. free Let\'s Encrypt) and redirect every page to HTTPS. Google and browsers flag non-HTTPS sites as not secure.',
                'Homepage resolved to a non-HTTPS URL', ['url' => $d['site']['url'] ?? null], 'high')] : [],

            'site-no-viewport' => fn ($d) => (($d['site']['status'] ?? 0) && empty($d['site']['hasViewport'])) ? [$this->s($C['tech'],
                'واکنش‌گرا کردن سایت برای موبایل', 'Make the site mobile responsive',
                'تگ viewport در صفحه اصلی پیدا نشد؛ یعنی سایت احتمالاً در موبایل درست نمایش داده نمی‌شود. از توسعه‌دهنده بخواه سایت را واکنش‌گرا کند و در گوشی تست کن.',
                'No viewport meta tag was found on the homepage, so the site probably does not render properly on phones. Ask your developer to make it responsive and test on a phone.',
                'Homepage has no <meta name="viewport">', null, 'high')] : [],

            'site-no-meta' => fn ($d) => (($d['site']['status'] ?? 0) && empty($d['site']['metaDesc'])) ? [$this->s($C['seo'],
                'نوشتن توضیحات متا برای صفحه اصلی', 'Write a meta description for the homepage',
                'صفحه اصلی توضیحات متا ندارد. یک توضیح ۱۴۰ تا ۱۶۰ کاراکتری بنویس که بگوید چه کاری برای چه کسی انجام می‌دهی و با یک دعوت به اقدام تمام شود.',
                'The homepage has no meta description. Write one of 140 to 160 characters saying what you do, for whom, ending with a call to action.',
                'Homepage has no meta description', ['title' => $d['site']['title'] ?? null])] : [],

            'gsc-missing' => fn ($d) => (empty($d['gsc']) && empty($d['errors']['gsc'])) ? [$this->s($C['setup'],
                'اتصال Google Search Console', 'Connect Google Search Console',
                'سایت هنوز به Search Console وصل نیست. در صفحه خودت دکمه «اتصال به گوگل» را بزن و سایت را انتخاب کن تا ببینیم با چه عبارت‌هایی پیدا می‌شوی.',
                'The site is not connected to Search Console yet. Use "Connect Google" on your page and pick the site so we can see which searches bring people to you.',
                'No Search Console data', null, 'high')] : [],

            'gsc-low-ctr' => function ($d) use ($C) {
                $out = [];
                foreach (array_slice(array_filter($d['gsc']['pages'] ?? [], fn ($p) => $p['impressions'] >= 200 && $p['ctr'] < 2 && $p['position'] <= 20), 0, 5) as $p) {
                    $out[] = $this->s($C['seo'],
                        'بازنویسی عنوان و توضیحات متا: '.$this->short($p['key']), 'Rewrite title & meta description: '.$this->short($p['key']),
                        "این صفحه {$p['impressions']} بار در نتایج گوگل دیده شده ولی فقط {$p['ctr']}% کلیک گرفته (جایگاه میانگین {$p['position']}). عنوان را جذاب‌تر و توضیحات متا را با یک دعوت به اقدام بنویس.",
                        "This page had {$p['impressions']} impressions but only {$p['ctr']}% CTR (avg position {$p['position']}). Make the title more compelling and add a call to action in the meta description.",
                        'High impressions, CTR under 2%', $p);
                }

                return $out;
            },

            'gsc-striking-distance' => function ($d) use ($C) {
                $out = [];
                foreach (array_slice(array_filter($d['gsc']['queries'] ?? [], fn ($q) => $q['position'] >= 5 && $q['position'] <= 15 && $q['impressions'] >= 100), 0, 5) as $q) {
                    $k = $this->short($q['key'], 40);
                    $out[] = $this->s($C['seo'],
                        "تقویت محتوا برای عبارت «{$k}»", "Strengthen content for \"{$k}\"",
                        "عبارت «{$q['key']}» با {$q['impressions']} نمایش در جایگاه {$q['position']} است. با افزودن یک بخش اختصاصی، پاسخ مستقیم به این سوال و چند لینک داخلی می‌تواند به صفحه اول برسد.",
                        "\"{$q['key']}\" has {$q['impressions']} impressions at position {$q['position']}. Add a dedicated section, a direct answer and internal links to push it onto page one.",
                        'Query ranks on page 2 with meaningful impressions', $q);
                }

                return $out;
            },

            'ga4-missing' => fn ($d) => (empty($d['ga4']) && empty($d['errors']['ga4'])) ? [$this->s($C['setup'],
                'اتصال Google Analytics 4', 'Connect Google Analytics 4',
                'هنوز GA4 وصل نیست. بعد از اتصال گوگل، پراپرتی GA4 را در صفحه خودت انتخاب کن تا رفتار کاربران را ببینیم.',
                'GA4 is not connected yet. After connecting Google, pick the GA4 property on your page so we can see user behaviour.',
                'No GA4 data', null, 'high')] : [],

            'ga4-low-engagement-page' => function ($d) use ($C) {
                $out = [];
                foreach (array_slice(array_filter($d['ga4']['pages'] ?? [], fn ($p) => $p['views'] >= 100 && $p['engagementRate'] < 35), 0, 5) as $p) {
                    $out[] = $this->s($C['ux'],
                        'بهبود محتوا و تجربه صفحه '.$this->short($p['path']), 'Improve content & UX of '.$this->short($p['path']),
                        "این صفحه {$p['views']} بازدید داشته ولی فقط {$p['engagementRate']}% کاربران درگیر شده‌اند. بالای صفحه را بازنویسی کن، سرعت بارگذاری را چک کن و یک مرحله بعدی واضح (دکمه یا لینک) اضافه کن.",
                        "This page had {$p['views']} views but only {$p['engagementRate']}% engagement. Rework the above-the-fold content, check load speed and add a clear next step (button or link).",
                        'Popular page with engagement under 35%', $p);
                }

                return $out;
            },

            'ga4-mobile-engagement' => function ($d) use ($C) {
                $dev = collect($d['ga4']['devices'] ?? [])->keyBy('device');
                $m = $dev['mobile'] ?? null;
                $dk = $dev['desktop'] ?? null;
                if (! $m || ! $dk || $m['sessions'] < 100 || $m['engagementRate'] >= $dk['engagementRate'] - 15) {
                    return [];
                }

                return [$this->s($C['ux'],
                    'رفع مشکلات نسخه موبایل سایت', 'Fix mobile site issues',
                    "درگیری کاربران موبایل ({$m['engagementRate']}%) به‌وضوح کمتر از دسکتاپ ({$dk['engagementRate']}%) است. سایت را روی گوشی باز کن: اندازه متن، دکمه‌ها، منو و سرعت را بررسی و اصلاح کن.",
                    "Mobile engagement ({$m['engagementRate']}%) is clearly below desktop ({$dk['engagementRate']}%). Open the site on a phone and fix text size, buttons, menu and speed.",
                    'Mobile engagement 15+ points below desktop', ['mobile' => $m, 'desktop' => $dk], 'high')];
            },

            'ga4-no-conversions' => fn ($d) => (! empty($d['ga4']) && ($d['ga4']['totals']['sessions'] ?? 0) >= 200 && ($d['ga4']['totals']['conversions'] ?? 0) == 0) ? [$this->s($C['conversion'],
                'تعریف رویدادهای تبدیل (Conversion) در GA4', 'Set up conversion events in GA4',
                "در {$d['ga4']['totals']['sessions']} جلسه هیچ تبدیلی ثبت نشده. حداقل یک رویداد کلیدی (فرم تماس، تماس تلفنی، خرید) را در GA4 به‌عنوان Key event علامت بزن.",
                "No conversions recorded across {$d['ga4']['totals']['sessions']} sessions. Mark at least one key event (contact form, phone call, purchase) as a Key event in GA4.",
                'Sessions but zero conversions tracked', $d['ga4']['totals'], 'high')] : [],

            'clarity-missing' => fn ($d) => (empty($d['clarity']) && empty($d['errors']['clarity'])) ? [$this->s($C['setup'],
                'اتصال Microsoft Clarity', 'Connect Microsoft Clarity',
                'Clarity رایگان است و نشان می‌دهد کاربران دقیقاً کجا گیر می‌کنند. در Clarity یک پروژه بساز، کد را در سایت بگذار و API token را در صفحه خودت وارد کن.',
                'Clarity is free and shows exactly where users get stuck. Create a project, add the snippet to the site and paste the API token on your page.',
                'No Clarity data')] : [],

            'clarity-rage-clicks' => function ($d) use ($C) {
                $out = [];
                foreach (array_slice(array_filter($d['clarity']['rageClicks'] ?? [], fn ($r) => $r['value'] >= 3), 0, 3) as $r) {
                    $out[] = $this->s($C['ux'],
                        'رفع کلیک‌های عصبی (rage click) در '.$this->short($r['url']), 'Fix rage clicks on '.$this->short($r['url']),
                        "در {$r['value']}% از جلسات این صفحه، کاربران چند بار پشت سر هم روی یک نقطه کلیک کرده‌اند. در Clarity ضبط جلسات این صفحه را ببین و عنصری که کار نمی‌کند یا کند است را درست کن.",
                        "{$r['value']}% of sessions on this page had rage clicks. Watch the Clarity recordings for this page and fix the element that is broken or slow.",
                        'Rage clicks in 3%+ of sessions', $r, 'high');
                }

                return $out;
            },

            'clarity-dead-clicks' => function ($d) use ($C) {
                $out = [];
                foreach (array_slice(array_filter($d['clarity']['deadClicks'] ?? [], fn ($r) => $r['value'] >= 5), 0, 3) as $r) {
                    $out[] = $this->s($C['ux'],
                        'رفع کلیک‌های بی‌اثر (dead click) در '.$this->short($r['url']), 'Fix dead clicks on '.$this->short($r['url']),
                        "در {$r['value']}% از جلسات، کاربران روی چیزی کلیک کرده‌اند که واکنشی نداشته. احتمالاً متن یا تصویری شبیه لینک است. آن را لینک کن یا ظاهرش را تغییر بده.",
                        "{$r['value']}% of sessions had dead clicks: users clicked something that did nothing. Probably text or an image that looks clickable. Link it or change its styling.",
                        'Dead clicks in 5%+ of sessions', $r);
                }

                return $out;
            },

            'clarity-js-errors' => function ($d) use ($C) {
                $out = [];
                foreach (array_slice(array_filter($d['clarity']['jsErrors'] ?? [], fn ($r) => $r['value'] >= 5), 0, 2) as $r) {
                    $out[] = $this->s($C['tech'],
                        'رفع خطاهای جاوااسکریپت در '.$this->short($r['url']), 'Fix JavaScript errors on '.$this->short($r['url']),
                        "{$r['value']}% از جلسات این صفحه خطای اسکریپت داشته‌اند. کنسول مرورگر را باز کن و خطاها را برطرف کن یا به توسعه‌دهنده بده.",
                        "{$r['value']}% of sessions on this page hit a script error. Open the browser console and fix the errors or pass them to your developer.",
                        'Script errors in 5%+ of sessions', $r, 'high');
                }

                return $out;
            },

            'clarity-quick-backs' => function ($d) use ($C) {
                $out = [];
                foreach (array_slice(array_filter($d['clarity']['quickBacks'] ?? [], fn ($r) => $r['value'] >= 10), 0, 2) as $r) {
                    $out[] = $this->s($C['ux'],
                        'کاهش بازگشت سریع کاربران از '.$this->short($r['url']), 'Reduce quick backs from '.$this->short($r['url']),
                        "{$r['value']}% از کاربران بلافاصله از این صفحه برگشته‌اند؛ یعنی چیزی که انتظار داشتند را ندیده‌اند. مطمئن شو عنوان لینک و محتوای صفحه هم‌خوانی دارند و محتوای اصلی بالای صفحه است.",
                        "{$r['value']}% of users left this page immediately, meaning it did not match what they expected. Make sure link text and page content match and the main content is above the fold.",
                        'Quick backs in 10%+ of sessions', $r);
                }

                return $out;
            },
        ];
    }
}
