@extends('layouts.super-admin')

@section('title', $business->exists ? 'Edit Business' : 'Create Business')

@section('content')
<div class="mb-8 flex items-start justify-between gap-6">
    <div>
        <a href="{{ route('super-admin.businesses') }}" class="text-sm font-medium text-emerald-700">&larr; Businesses</a>
        <h1 class="mt-2 text-3xl font-bold">{{ $business->exists ? 'Edit business' : 'Create business' }}</h1>
        <p class="mt-1 text-slate-600">Manage platform registration details only.</p>
    </div>
</div>

<form method="POST" action="{{ $business->exists ? route('super-admin.businesses.update', $business) : route('super-admin.businesses.store') }}" class="grid gap-6 lg:grid-cols-2">
    @csrf
    @if($business->exists) @method('PUT') @endif
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-bold">Business information</h2>
        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <label class="sm:col-span-2 text-sm font-medium">Business name
                <input name="name" value="{{ old('name', $business->name) }}" required class="mt-1 w-full rounded-lg border-slate-300" />
            </label>
            <label class="text-sm font-medium">Type
                <select name="business_type" required class="mt-1 w-full rounded-lg border-slate-300">
                    @foreach(['cafe' => 'Coffee / Milk Tea Shop', 'restaurant' => 'Restaurant', 'retail' => 'Retail', 'service' => 'Service', 'other' => 'Other'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('business_type', $business->business_type ?: 'cafe') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium">Status
                <select name="status" required class="mt-1 w-full rounded-lg border-slate-300">
                    @foreach(['pending', 'active', 'suspended', 'inactive'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $business->status ?: 'pending') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium">Business email
                <input type="email" name="email" value="{{ old('email', $business->email) }}" class="mt-1 w-full rounded-lg border-slate-300" />
            </label>
            <label class="text-sm font-medium">Phone
                <input name="phone" value="{{ old('phone', $business->phone) }}" class="mt-1 w-full rounded-lg border-slate-300" />
            </label>
            <label class="sm:col-span-2 text-sm font-medium">Address
                <input name="address" value="{{ old('address', $business->address) }}" class="mt-1 w-full rounded-lg border-slate-300" />
            </label>
            <label class="text-sm font-medium">City
                <input name="city" value="{{ old('city', $business->city) }}" class="mt-1 w-full rounded-lg border-slate-300" />
            </label>
            <label class="text-sm font-medium">Province
                <input name="province" value="{{ old('province', $business->province) }}" class="mt-1 w-full rounded-lg border-slate-300" />
            </label>
            <label class="sm:col-span-2 text-sm font-medium">Description
                <textarea name="description" rows="3" class="mt-1 w-full rounded-lg border-slate-300">{{ old('description', $business->description) }}</textarea>
            </label>
            <label class="sm:col-span-2 text-sm font-medium">Status reason
                <textarea name="suspension_reason" rows="2" class="mt-1 w-full rounded-lg border-slate-300">{{ old('suspension_reason', $business->suspension_reason) }}</textarea>
            </label>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-bold">Owner account</h2>
        <div class="mt-5 grid gap-4">
            <label class="text-sm font-medium">Owner name
                <input name="owner_name" value="{{ old('owner_name', $business->owner_account?->name) }}" required class="mt-1 w-full rounded-lg border-slate-300" />
            </label>
            <label class="text-sm font-medium">Owner email
                <input type="email" name="owner_email" value="{{ old('owner_email', $business->owner_account?->email) }}" required class="mt-1 w-full rounded-lg border-slate-300" />
            </label>
            <label class="text-sm font-medium">Owner phone
                <input name="owner_phone" value="{{ old('owner_phone', $business->owner_account?->phone) }}" class="mt-1 w-full rounded-lg border-slate-300" />
            </label>
            @unless($business->exists)
                <label class="text-sm font-medium">Temporary password
                    <input type="password" name="password" required class="mt-1 w-full rounded-lg border-slate-300" />
                </label>
                <label class="text-sm font-medium">Confirm password
                    <input type="password" name="password_confirmation" required class="mt-1 w-full rounded-lg border-slate-300" />
                </label>
            @endunless
            <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-800">This form never displays or modifies the business's financial and operational records.</div>
        </div>
    </section>
    <div class="lg:col-span-2 flex justify-end gap-3">
        <a href="{{ route('super-admin.businesses') }}" class="rounded-lg border border-slate-300 px-5 py-3 font-medium">Cancel</a>
        <button class="rounded-lg bg-emerald-600 px-5 py-3 font-semibold text-white hover:bg-emerald-700">{{ $business->exists ? 'Save changes' : 'Create business' }}</button>
    </div>
</form>
@endsection
