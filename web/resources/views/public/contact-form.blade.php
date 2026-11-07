@if(session('contact_sent'))
    <p role="status" class="rounded-xl border border-[#B7DAD0] bg-[#EAF8F2] px-4 py-3 text-sm font-semibold text-[#065A49]">{{ session('contact_sent') }}</p>
@endif
@if(session('contact_error'))
    <p role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ session('contact_error') }}</p>
@endif
@if($errors->any())
    <p role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">Please check the highlighted fields and try again.</p>
@endif

<form method="POST" action="{{ route('contact.send') }}" class="mt-6 space-y-4">
    @csrf
    @if(($source ?? null) === 'landing')
        <input type="hidden" name="source" value="landing">
    @endif
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="contact-name" class="block text-sm font-bold">Your name</label>
            <input id="contact-name" name="name" type="text" autocomplete="name" required minlength="2" maxlength="100" value="{{ old('name') }}" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" @if($errors->has('name')) aria-describedby="contact-name-error" @endif class="gt-focus mt-2 w-full rounded-xl border border-[#B7DAD0] bg-white px-4 py-3 text-sm text-[#0A2E2A] placeholder:text-slate-400 focus:border-[#08745D] focus:ring-[#08745D]" placeholder="Your name">
            @error('name') <p id="contact-name-error" class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="contact-email" class="block text-sm font-bold">Email address</label>
            <input id="contact-email" name="email" type="email" autocomplete="email" required maxlength="254" value="{{ old('email') }}" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" @if($errors->has('email')) aria-describedby="contact-email-error" @endif class="gt-focus mt-2 w-full rounded-xl border border-[#B7DAD0] bg-white px-4 py-3 text-sm text-[#0A2E2A] placeholder:text-slate-400 focus:border-[#08745D] focus:ring-[#08745D]" placeholder="you@example.com">
            @error('email') <p id="contact-email-error" class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
        </div>
    </div>
    <div>
        <label for="contact-subject" class="block text-sm font-bold">Subject</label>
        <input id="contact-subject" name="subject" type="text" required minlength="3" maxlength="120" value="{{ old('subject') }}" aria-invalid="{{ $errors->has('subject') ? 'true' : 'false' }}" @if($errors->has('subject')) aria-describedby="contact-subject-error" @endif class="gt-focus mt-2 w-full rounded-xl border border-[#B7DAD0] bg-white px-4 py-3 text-sm text-[#0A2E2A] placeholder:text-slate-400 focus:border-[#08745D] focus:ring-[#08745D]" placeholder="What can we help with?">
        @error('subject') <p id="contact-subject-error" class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="contact-message" class="block text-sm font-bold">Message</label>
        <textarea id="contact-message" name="message" rows="5" required minlength="10" maxlength="5000" aria-invalid="{{ $errors->has('message') ? 'true' : 'false' }}" @if($errors->has('message')) aria-describedby="contact-message-error" @endif class="gt-focus mt-2 w-full rounded-xl border border-[#B7DAD0] bg-white px-4 py-3 text-sm text-[#0A2E2A] placeholder:text-slate-400 focus:border-[#08745D] focus:ring-[#08745D]" placeholder="Describe your question or issue">{{ old('message') }}</textarea>
        @error('message') <p id="contact-message-error" class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
    </div>
    <div class="sr-only" aria-hidden="true"><label for="contact-website">Website</label><input id="contact-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
    <button type="submit" class="gt-focus inline-flex min-h-12 items-center justify-center rounded-xl bg-[#08745D] px-6 py-3 text-sm font-extrabold text-white transition hover:bg-[#065A49]">Send message</button>
</form>
