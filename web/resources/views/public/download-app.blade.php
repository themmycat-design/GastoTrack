@php
    $apkDownloadUrl = trim((string) config('services.gastotrack.android_apk_url'));
    $hasApkDownload = filter_var($apkDownloadUrl, FILTER_VALIDATE_URL)
        && parse_url($apkDownloadUrl, PHP_URL_SCHEME) === 'https';
    $apkQrDataUri = null;
    if ($hasApkDownload) {
        $qrWriter = new \BaconQrCode\Writer(new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle(240, 4),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
        ));
        $apkQrDataUri = 'data:image/svg+xml;base64,'.base64_encode($qrWriter->writeString($apkDownloadUrl));
    }
@endphp
<x-layouts.public-info title="Download GastoTrack Staff" intro="Follow these steps to install the staff Android app for the GastoTrack demonstration." description="Download and install the GastoTrack Staff Android APK.">
    <section>
        <h2 class="text-xl font-extrabold">Get the APK</h2>
        @if($hasApkDownload)
            <div class="mt-4 flex flex-col items-start gap-6 sm:flex-row sm:items-center">
                <div class="max-w-sm">
                    <p class="text-sm leading-7 text-slate-600">Open this page on your Android phone and tap the button, or scan the QR code with your phone's camera to open the same download link.</p>
                    <a href="{{ $apkDownloadUrl }}" class="gt-focus mt-5 inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-[#08745D] px-5 py-3 text-sm font-extrabold text-white transition hover:bg-[#065A49]">
                        Download APK <span aria-hidden="true">&#8595;</span>
                    </a>
                </div>
                <figure class="rounded-2xl border border-[#DDE9E5] bg-white p-3 text-center shadow-sm">
                    <img src="{{ $apkQrDataUri }}" alt="QR code for the GastoTrack Staff APK download" width="240" height="240" class="h-48 w-48 sm:h-52 sm:w-52">
                    <figcaption class="mt-2 text-xs font-semibold text-slate-600">Scan to download</figcaption>
                </figure>
            </div>
        @else
            <p class="mt-2 text-sm leading-7 text-slate-600">The APK download link is not available here yet. Ask the GastoTrack team for the current EAS tester build link. The button will appear here when a direct download is published.</p>
            <a href="{{ route('contact') }}" class="gt-focus mt-5 inline-flex min-h-12 items-center justify-center rounded-xl border border-[#B7DAD0] px-5 py-3 text-sm font-extrabold text-[#08745D] transition hover:bg-[#EAF8F2]">Contact the team</a>
        @endif
    </section>
    <section class="border-t border-[#DDE9E5] pt-7">
        <h2 class="text-xl font-extrabold">Install on your phone</h2>
        <ol class="mt-4 space-y-4 text-sm leading-7 text-slate-600">
            <li><span class="font-extrabold text-[#08745D]">1.</span> Open the downloaded APK from your phone's Downloads folder.</li>
            <li><span class="font-extrabold text-[#08745D]">2.</span> Follow Android's installation prompts. Android may ask to allow installs from the browser or file manager you used. A Play Protect block is different; follow the steps below if that appears.</li>
            <li><span class="font-extrabold text-[#08745D]">3.</span> Open GastoTrack and sign in with the staff account created by your business owner.</li>
        </ol>
        <p class="mt-5 rounded-xl border border-[#DDE9E5] bg-[#F4F8F7] px-4 py-3 text-sm leading-6 text-slate-600">If GastoTrack is already installed, install the new APK as an update. Sync any unsent offline records before uninstalling an older copy of the app.</p>
    </section>
    <section class="border-t border-[#DDE9E5] pt-7" aria-labelledby="play-protect-help">
        <h2 id="play-protect-help" class="text-xl font-extrabold">Blocked by Google Play Protect?</h2>
        <p class="mt-2 text-sm leading-7 text-slate-600">If your phone says <strong>“App blocked to protect your device”</strong>, stop the APK installation. Changing the browser's install permission will not resolve this warning.</p>
        <ol class="mt-4 space-y-4 text-sm leading-7 text-slate-600">
            <li><span class="font-extrabold text-[#08745D]">1.</span> Take a screenshot of the warning and <a href="{{ route('contact') }}" class="gt-focus font-bold text-[#08745D] underline underline-offset-2">contact the GastoTrack team</a>. Include your phone model and Android version; do not send passwords or payment details.</li>
            <li><span class="font-extrabold text-[#08745D]">2.</span> If the team provides a Google Play internal testing invitation, open its opt-in link with the Google account added as a tester. Join the test, then install GastoTrack from its Play Store link.</li>
            <li><span class="font-extrabold text-[#08745D]">3.</span> If no Play testing link is available, wait for the team to review the blocked build and provide another approved way to install it.</li>
        </ol>
        <p class="mt-5 rounded-xl border border-[#B7DAD0] bg-[#EAF8F2] px-4 py-3 text-sm leading-6 text-[#065A49]">Turning Play Protect back on can trigger another warning or disable an app it flags. Keep Play Protect enabled and ask the project team for a Play testing link. The team can review the app's permissions and request a Google review if the block is incorrect.</p>
    </section>
    <section class="border-t border-[#DDE9E5] pt-7">
        <h2 class="text-xl font-extrabold">Need help?</h2>
        <p class="mt-2 text-sm leading-7 text-slate-600">If the download link is missing or installation is blocked, <a href="{{ route('contact') }}" class="gt-focus font-bold text-[#08745D] underline underline-offset-2">contact the GastoTrack team</a> for the current tester build and assistance.</p>
    </section>
</x-layouts.public-info>
