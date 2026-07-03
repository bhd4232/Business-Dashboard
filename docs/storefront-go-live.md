# Storefront Production Domain Go-Live গাইড (DNS + Coolify SSL)

কোড-লেভেল কাজ সম্পন্ন (`ResolveCompanyFromDomain` middleware রেডি)। এই গাইডটা শুধু ইনফ্রাস্ট্রাকচার ধাপ — Coolify dashboard ও প্রতিটা domain-এর DNS প্যানেল থেকে করতে হবে।

## Domain ↔ Company ম্যাপিং

| Company | Domain |
|---|---|
| Garments Machinery | tasneemknitindustry.com |
| Solar Items | noorsolaren.com |
| Gadget Items | zamzamgadgetbd.com |
| Gift Items | zamzamint.com |

## ধাপ ০ — Go-live-এর আগে (আবশ্যক)

1. **Database backup নিন** (release-policy অনুযায়ী migration/launch-এর আগে বাধ্যতামূলক)।
2. `CHANGELOG.md`-এ storefront launch entry যোগ করুন (type: major)।
3. **WooCommerce migration সিদ্ধান্ত:** পুরনো সাইটের customer/product ডেটা এখনো আনা হয়নি (Part 12)। DNS switch করলেই পুরনো WooCommerce সাইট বন্ধ হয়ে যাবে — তাই হয় আগে ডেটা migrate করুন, নয়তো এক domain দিয়ে phased rollout করুন।
4. Filament-এ প্রতিটা company-র:
   - `domain` ফিল্ড সঠিকভাবে পূরণ আছে কিনা চেক করুন (www ছাড়া, ঠিক যেমন উপরের টেবিলে)।
   - `StorefrontSettings`-এ `is_published` on এবং launch-readiness চেক পাস করছে কিনা দেখুন।
   - Product/Category ডেটা রেডি কিনা দেখুন (খালি storefront launch করবেন না)।

## ধাপ ১ — Coolify-তে domain যোগ করা

1. Coolify dashboard → আপনার ERP Application → **Settings/Domains**।
2. বিদ্যমান `app.zamzamint.com`-এর পাশে ৪টা domain কমা দিয়ে যোগ করুন:
   ```
   https://tasneemknitindustry.com,https://www.tasneemknitindustry.com,
   https://noorsolaren.com,https://www.noorsolaren.com,
   https://zamzamgadgetbd.com,https://www.zamzamgadgetbd.com,
   https://zamzamint.com,https://www.zamzamint.com
   ```
   (www ভ্যারিয়েন্টও যোগ করুন; নিচে ধাপ ৪-এ redirect নোট দেখুন।)
3. Save করুন — এখনই redeploy করবেন না, আগে DNS পয়েন্ট করান।

## ধাপ ২ — DNS রেকর্ড (প্রতিটা domain-এর registrar/DNS প্যানেলে)

প্রতিটা domain-এর জন্য:

```
Type  Name   Value                  TTL
A     @      <Coolify সার্ভারের IP>   300
CNAME www    @  (বা A রেকর্ড একই IP)  300
```

- বর্তমানে WooCommerce hosting-এ পয়েন্ট করা পুরনো A রেকর্ড replace করুন।
- Cloudflare ব্যবহার করলে শুরুতে proxy **off (DNS only)** রাখুন — Let's Encrypt issue হওয়ার পর চাইলে on করবেন।
- Propagation চেক: `nslookup tasneemknitindustry.com` → Coolify IP দেখাচ্ছে কিনা।

## ধাপ ৩ — SSL

DNS resolve হওয়ার পর Coolify-তে application redeploy/restart করুন — Coolify (Traefik/Caddy) প্রতিটা domain-এর জন্য Let's Encrypt certificate স্বয়ংক্রিয়ভাবে issue করবে। ব্যর্থ হলে চেক করুন: DNS propagate হয়েছে কিনা, port 80/443 খোলা কিনা, এবং Cloudflare proxy off আছে কিনা।

## ধাপ ৪ — অ্যাপ কনফিগ

1. `.env`-এ কিছু বদলাতে হবে না — middleware runtime-এ host দেখে company resolve করে।
2. তবে `APP_URL` admin domain-ই (`https://app.zamzamint.com`) থাকবে।
3. www → apex redirect Coolify/proxy লেভেলে সেট করুন (নয়তো `www.` host DB-তে match না করে 404 দেবে)। বিকল্প: `companies.domain`-এ শুধু apex রাখুন এবং proxy-তে redirect rule দিন।

## ধাপ ৫ — Verify checklist (প্রতিটা domain)

```
[ ] https:// লোড হয়, সঠিক company-র নাম/লোগো/থিম দেখায়
[ ] অন্য company-র product দেখা যায় না
[ ] Product browse → cart → checkout → order তৈরি হয় (draft, stock deduct হয় না)
[ ] Admin panel-এ order টা Storefront source badge সহ দেখা যায়
[ ] /track/{orderNo} ও /account/orders কাজ করে
[ ] SSL padlock ভ্যালিড (দুই www/apex ভ্যারিয়েন্টেই)
[ ] Filament-এ company-র domain_verified toggle on করুন
```

## Rollback

সমস্যা হলে: DNS A রেকর্ড পুরনো WooCommerce hosting-এ ফিরিয়ে দিন (TTL 300 রাখলে ~৫ মিনিটে ফিরে যাবে)। ERP অপরিবর্তিত থাকে।
