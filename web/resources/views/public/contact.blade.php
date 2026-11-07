<x-layouts.public-info title="Contact" intro="Questions about GastoTrack, your account, or a privacy request? Send the team an email and include enough detail for us to understand the issue." description="Contact the GastoTrack project team.">
    <section>
        <h2 class="text-xl font-extrabold">Send a message</h2>
        <p class="mt-2 text-sm leading-7 text-slate-600">Fill out the form and we will receive your message by email. Please do not include passwords, payment codes, or private transaction details.</p>
        <div class="mt-5">@include('public.contact-form')</div>
    </section>
    <section class="border-t border-[#DDE9E5] pt-7">
        <h2 class="text-xl font-extrabold">Email directly</h2>
        <p class="mt-2 text-sm leading-7 text-slate-600">If the form is unavailable, email the project team at <a href="mailto:paghari-oncc.694.stud@cdd.edu.ph" class="gt-focus break-all font-bold text-[#08745D] underline underline-offset-2">paghari-oncc.694.stud@cdd.edu.ph</a>.</p>
    </section>
    <section class="border-t border-[#DDE9E5] pt-7">
        <h2 class="text-xl font-extrabold">Reporting a problem</h2>
        <p class="mt-2 text-sm leading-7 text-slate-600">Business owners can submit a bug report from the owner portal. Staff can submit one from the Android app. Include what you were doing and what happened; do not include passwords or one-time codes.</p>
        <div class="mt-4 flex flex-wrap gap-3">
            <a href="{{ route('login') }}" class="gt-focus rounded-xl border border-[#B7DAD0] px-4 py-2.5 text-sm font-bold text-[#08745D] hover:bg-[#EAF8F2]">Owner sign in</a>
            <a href="{{ route('privacy') }}" class="gt-focus rounded-xl border border-[#B7DAD0] px-4 py-2.5 text-sm font-bold text-[#08745D] hover:bg-[#EAF8F2]">Read Privacy Policy</a>
        </div>
    </section>
</x-layouts.public-info>
