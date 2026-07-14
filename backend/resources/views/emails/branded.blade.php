{{-- Shared branded email shell: warm light card, Väinö on top, one amber CTA.
     Table layout + inline styles only - that's what survives Outlook/Gmail.
     Variables: $vaino (public image filename), $title, $intro (array of lines),
     $actionUrl + $actionText (optional), $outro (array of lines). --}}
@php($base = rtrim(config('services.stripe.frontend_url') ?: config('app.url'), '/'))
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <title>{{ $title }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f6f1e7; font-family:-apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
  {{-- Preheader: the inbox preview line; hidden inside the mail itself. --}}
  <div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">{{ $preheader ?? $intro[0] ?? '' }}</div>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f6f1e7; padding:32px 12px;">
    <tr><td align="center">

      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;">
        {{-- Brand header --}}
        <tr><td align="center" style="padding-bottom:18px;">
          <a href="{{ $base }}" style="text-decoration:none;">
            <img src="{{ $base }}/logo-sm.png" width="44" height="44" alt="SaunaSpeak" style="border-radius:10px; display:inline-block; vertical-align:middle;">
            <span style="font-size:20px; font-weight:800; color:#29241d; vertical-align:middle; padding-left:10px; letter-spacing:-0.01em;">SaunaSpeak</span>
          </a>
        </td></tr>

        {{-- Card --}}
        <tr><td style="background-color:#fffdf8; border:1px solid #e8dfcd; border-radius:16px; padding:34px 30px; text-align:center;">
          <img src="{{ $base }}/{{ $vaino ?? 'vaino-wave.png' }}" width="110" height="110" alt="Väinö, your sauna companion" style="display:block; margin:0 auto 14px;">

          <h1 style="margin:0 0 14px; font-size:23px; line-height:1.25; color:#29241d;">{{ $title }}</h1>

          @foreach ($intro as $line)
            <p style="margin:0 0 12px; font-size:15px; line-height:1.6; color:#5c554a;">{!! $line !!}</p>
          @endforeach

          @isset($actionUrl)
            <table role="presentation" cellpadding="0" cellspacing="0" align="center" style="margin:22px auto 8px;">
              <tr><td style="border-radius:12px; background-color:#f59e0b;">
                <a href="{{ $actionUrl }}" style="display:inline-block; padding:14px 34px; font-size:16px; font-weight:700; color:#1a1204; text-decoration:none; border-radius:12px;">{{ $actionText }}</a>
              </td></tr>
            </table>
            {{-- Plain link for clients that strip buttons --}}
            <p style="margin:14px 0 0; font-size:12px; line-height:1.5; color:#9a8f7d; word-break:break-all;">
              Button not working? Copy this link:<br>
              <a href="{{ $actionUrl }}" style="color:#b45f0d;">{{ $actionUrl }}</a>
            </p>
          @endisset

          @foreach ($outro ?? [] as $line)
            <p style="margin:16px 0 0; font-size:13px; line-height:1.6; color:#9a8f7d;">{!! $line !!}</p>
          @endforeach
        </td></tr>

        {{-- Footer --}}
        <tr><td align="center" style="padding:20px 10px 0;">
          <p style="margin:0 0 6px; font-size:12px; line-height:1.6; color:#9a8f7d;">
            Kiitos, and see you in the sauna! 🧖
          </p>
          <p style="margin:0; font-size:11.5px; line-height:1.6; color:#b3a892;">
            <a href="{{ $base }}" style="color:#9a8f7d;">saunaspeak.com</a> &nbsp;·&nbsp;
            <a href="{{ $base }}/privacy" style="color:#9a8f7d;">Privacy</a> &nbsp;·&nbsp;
            <a href="{{ $base }}/terms" style="color:#9a8f7d;">Terms</a> &nbsp;·&nbsp;
            <a href="mailto:mail@saunaspeak.com" style="color:#9a8f7d;">mail@saunaspeak.com</a>
          </p>
          @isset($footerNote)
            <p style="margin:8px 0 0; font-size:11.5px; line-height:1.6; color:#b3a892;">{!! $footerNote !!}</p>
          @endisset
        </td></tr>
      </table>

    </td></tr>
  </table>
</body>
</html>
