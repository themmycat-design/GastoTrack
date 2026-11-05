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
            <p class="mt-2 text-sm leading-7 text-slate-600">The APK download link is not available here yet. Installation can begin when the GastoTrack team publishes the official APK on this page.</p>
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
        <p class="mt-2 text-sm leading-7 text-slate-600"><strong>Why it happens:</strong> GastoTrack uses notification access to detect GCash and Maya payment alerts. Google may block a browser-downloaded APK that requests this access. If you choose to install this project build for testing, you can temporarily turn off Play Protect scanning on your own phone:</p>
        <ol class="mt-4 space-y-4 text-sm leading-7 text-slate-600">
            <li><span class="font-extrabold text-[#08745D]">1.</span> Open <strong>Google Play Store</strong>. Tap your profile picture, then <strong>Play Protect</strong>, then the <strong>Settings</strong> gear.</li>
            <li><span class="font-extrabold text-[#08745D]">2.</span> Turn off <strong>Scan apps with Play Protect</strong> and confirm the prompt. This temporarily stops Play Protect scanning apps on your phone, not just GastoTrack.</li>
            <li><span class="font-extrabold text-[#08745D]">3.</span> Download GastoTrack only from the APK link or QR code on this page. Open the APK from Downloads, allow installation from your browser or file manager if Android asks, then install it.</li>
            <li><span class="font-extrabold text-[#08745D]">4.</span> Return to <strong>Google Play Store → Play Protect → Settings</strong> and turn <strong>Scan apps with Play Protect</strong> back on.</li>
        </ol>
        <p class="mt-5 rounded-xl border border-[#B7DAD0] bg-[#EAF8F2] px-4 py-3 text-sm leading-6 text-[#065A49]"><strong>Warning:</strong> Disabling Play Protect reduces protection for all apps while it is off. Turning it back on may warn about or disable GastoTrack again. If installation is still blocked, stop and <a href="{{ route('contact') }}" class="gt-focus font-bold underline underline-offset-2">contact the GastoTrack team</a>; this workaround is not guaranteed. <a href="https://support.google.com/googleplay/answer/2812853" target="_blank" rel="noopener noreferrer" class="gt-focus font-bold underline underline-offset-2">Google's Play Protect instructions</a>.</p>
    </section>
    <section class="border-t border-[#DDE9E5] pt-7">
        <h2 class="text-xl font-extrabold">Need help?</h2>
        <p class="mt-2 text-sm leading-7 text-slate-600">If the download link is missing or installation is still blocked, <a href="{{ route('contact') }}" class="gt-focus font-bold text-[#08745D] underline underline-offset-2">contact the GastoTrack team</a> for assistance.</p>
    </section>
</x-layouts.public-info>
