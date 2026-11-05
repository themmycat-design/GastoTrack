@php
    $apkDownloadUrl = trim((string) config('services.gastotrack.android_apk_url'));
    $hasApkDownload = filter_var($apkDownloadUrl, FILTER_VALIDATE_URL)
        && parse_url($apkDownloadUrl, PHP_URL_SCHEME) === 'https';
@endphp
<x-layouts.public-info title="Download GastoTrack Staff" intro="Follow these steps to install the staff Android app for the GastoTrack demonstration." description="Download and install the GastoTrack Staff Android APK.">
    <section>
        <h2 class="text-xl font-extrabold">Get the APK</h2>
        @if($hasApkDownload)
            <p class="mt-2 text-sm leading-7 text-slate-600">Open this page on your Android phone, then tap the button below to download the tester APK.</p>
            <a href="{{ $apkDownloadUrl }}" class="gt-focus mt-5 inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-[#08745D] px-5 py-3 text-sm font-extrabold text-white transition hover:bg-[#065A49]">
                Download APK <span aria-hidden="true">&#8595;</span>
            </a>
        @else
            <p class="mt-2 text-sm leading-7 text-slate-600">The APK download link is not available here yet. Ask the GastoTrack team for the current EAS tester build link. The button will appear here when a direct download is published.</p>
            <a href="{{ route('contact') }}" class="gt-focus mt-5 inline-flex min-h-12 items-center justify-center rounded-xl border border-[#B7DAD0] px-5 py-3 text-sm font-extrabold text-[#08745D] transition hover:bg-[#EAF8F2]">Contact the team</a>
        @endif
    </section>
    <section class="border-t border-[#DDE9E5] pt-7">
        <h2 class="text-xl font-extrabold">Install on your phone</h2>
        <ol class="mt-4 space-y-4 text-sm leading-7 text-slate-600">
            <li><span class="font-extrabold text-[#08745D]">1.</span> Open the downloaded APK from your phone's Downloads folder.</li>
            <li><span class="font-extrabold text-[#08745D]">2.</span> Follow Android's installation prompts. If Android asks, allow installation from the browser or file manager you used for this download.</li>
            <li><span class="font-extrabold text-[#08745D]">3.</span> Open GastoTrack and sign in with the staff account created by your business owner.</li>
        </ol>
        <p class="mt-5 rounded-xl border border-[#DDE9E5] bg-[#F4F8F7] px-4 py-3 text-sm leading-6 text-slate-600">If GastoTrack is already installed, install the new APK as an update. Sync any unsent offline records before uninstalling an older copy of the app.</p>
    </section>
    <section class="border-t border-[#DDE9E5] pt-7">
        <h2 class="text-xl font-extrabold">Need help?</h2>
        <p class="mt-2 text-sm leading-7 text-slate-600">If the download link is missing or installation is blocked, <a href="{{ route('contact') }}" class="gt-focus font-bold text-[#08745D] underline underline-offset-2">contact the GastoTrack team</a> for the current tester build and assistance.</p>
    </section>
</x-layouts.public-info>
