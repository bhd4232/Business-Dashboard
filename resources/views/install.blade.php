<x-layouts.marketing title="Install - ZamZam ERP">
    <main class="section">
        <h1>Installer</h1>
        <p>Create the first company profile and Super Admin account.</p>

        @if ($errors->any())
            <div class="card" style="border-color:#fecaca;color:#991b1b;">
                {{ $errors->first() }}
            </div>
        @endif

        <form class="card" method="POST" action="{{ route('install.store') }}" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;">
            @csrf
            <label>Company Name<input name="company_name" value="{{ old('company_name', 'ZamZam ERP') }}" required style="width:100%;height:42px;margin-top:6px;padding:0 12px;border:1px solid #cbd5e1;border-radius:8px;"></label>
            <label>Company Email<input name="company_email" value="{{ old('company_email') }}" type="email" style="width:100%;height:42px;margin-top:6px;padding:0 12px;border:1px solid #cbd5e1;border-radius:8px;"></label>
            <label>Company Phone<input name="company_phone" value="{{ old('company_phone') }}" style="width:100%;height:42px;margin-top:6px;padding:0 12px;border:1px solid #cbd5e1;border-radius:8px;"></label>
            <label>Currency<input name="currency" value="{{ old('currency', 'BDT') }}" required style="width:100%;height:42px;margin-top:6px;padding:0 12px;border:1px solid #cbd5e1;border-radius:8px;"></label>
            <label>Timezone<input name="timezone" value="{{ old('timezone', 'Asia/Dhaka') }}" required style="width:100%;height:42px;margin-top:6px;padding:0 12px;border:1px solid #cbd5e1;border-radius:8px;"></label>
            <label>Date Format<input name="date_format" value="{{ old('date_format', 'd M Y') }}" required style="width:100%;height:42px;margin-top:6px;padding:0 12px;border:1px solid #cbd5e1;border-radius:8px;"></label>
            <label>Admin Name<input name="admin_name" value="{{ old('admin_name', 'Admin') }}" required style="width:100%;height:42px;margin-top:6px;padding:0 12px;border:1px solid #cbd5e1;border-radius:8px;"></label>
            <label>Admin Email<input name="admin_email" value="{{ old('admin_email') }}" type="email" required style="width:100%;height:42px;margin-top:6px;padding:0 12px;border:1px solid #cbd5e1;border-radius:8px;"></label>
            <label>Admin Password<input name="admin_password" type="password" required style="width:100%;height:42px;margin-top:6px;padding:0 12px;border:1px solid #cbd5e1;border-radius:8px;"></label>
            <label>Confirm Password<input name="admin_password_confirmation" type="password" required style="width:100%;height:42px;margin-top:6px;padding:0 12px;border:1px solid #cbd5e1;border-radius:8px;"></label>
            <label>License Key<input name="license_key" value="{{ old('license_key') }}" placeholder="ZZERP-XXXX-XXXX-XXXX" style="width:100%;height:42px;margin-top:6px;padding:0 12px;border:1px solid #cbd5e1;border-radius:8px;"></label>
            <label style="display:flex;align-items:center;gap:10px;margin-top:28px;"><input type="checkbox" name="demo_mode" value="1" @checked(old('demo_mode'))> Enable demo mode</label>
            <div style="grid-column:1/-1;display:flex;justify-content:flex-end;">
                <button class="button" type="submit">Complete Install</button>
            </div>
        </form>
    </main>
</x-layouts.marketing>
