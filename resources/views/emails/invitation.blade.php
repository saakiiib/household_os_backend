@php
  $companyName = \App\Models\Setting::get('company_name', 'Household OS');
  $logo = \App\Models\Setting::get('logo');
  $logoUrl = $logo ? asset($logo) : asset('logo.png');
  $appStoreUrl = \App\Models\Setting::get('app_store_url', 'https://apps.apple.com/app/household-os/id000000000');
  $playStoreUrl = \App\Models\Setting::get('play_store_url', 'https://play.google.com/store/apps/details?id=com.householdos.app');
  $tagline = \App\Models\Setting::get('footer_tagline', 'The operating system for modern family life.');
  $disclaimer = \App\Models\Setting::get('footer_disclaimer', 'This is an automated service email. Please do not share verification or reset codes with anyone.');
@endphp
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="x-apple-disable-message-reformatting">
  <title>You're invited to join {{ $householdName }} - {{ $companyName }}</title>
  <style>
    html, body { margin: 0 !important; padding: 0 !important; width: 100% !important; }
    * { box-sizing: border-box; }
    body {
      background: #F4F7FB;
      color: #10233F;
      font-family: Arial, Helvetica, sans-serif;
      -webkit-font-smoothing: antialiased;
    }
    table { border-collapse: collapse !important; border-spacing: 0 !important; }
    img { border: 0; line-height: 100%; outline: none; text-decoration: none; }
    a { color: #087DE3; }
    .wrapper { width: 100%; background: #F4F7FB; padding: 32px 14px; }
    .card {
      width: 100%;
      max-width: 620px;
      background: #FFFFFF;
      border-radius: 18px;
      box-shadow: 0 8px 28px rgba(16, 35, 63, 0.08);
      overflow: hidden;
    }
    .brand-line {
      height: 6px;
      background: linear-gradient(90deg, #087DE3 0%, #16B89A 100%);
      font-size: 0;
      line-height: 0;
    }
    .header { padding: 28px 36px 18px; text-align: center; }
    .logo { width: 280px; max-width: 100%; height: auto; display: inline-block; }
    .content { padding: 10px 42px 34px; }
    h1 {
      margin: 4px 0 14px;
      font-size: 27px;
      line-height: 1.25;
      color: #10233F;
      font-weight: 700;
    }
    p {
      margin: 0 0 16px;
      font-size: 16px;
      line-height: 1.65;
      color: #40506A;
    }
    .muted { color: #6D7A90; font-size: 14px; line-height: 1.55; }
    .info-box {
      margin: 20px 0;
      padding: 16px 18px;
      border-left: 4px solid #16B89A;
      border-radius: 0 10px 10px 0;
      background: #F3FBF8;
    }
    .steps {
      margin: 8px 0 22px;
      padding-left: 22px;
      color: #40506A;
      font-size: 16px;
      line-height: 1.65;
    }
    .app-buttons { text-align: center; padding: 4px 0 14px; }
    .app-link {
      display: inline-block;
      margin: 5px 4px;
      padding: 11px 16px;
      border: 1px solid #D8E7F6;
      border-radius: 10px;
      color: #10233F !important;
      text-decoration: none;
      font-size: 14px;
      font-weight: 700;
      background: #FFFFFF;
    }
    .divider { border-top: 1px solid #E8EEF5; margin: 24px 0; }
    .footer {
      max-width: 620px;
      padding: 20px 22px 0;
      text-align: center;
      color: #7C8799;
      font-size: 12px;
      line-height: 1.6;
    }
    @media screen and (max-width: 640px) {
      .wrapper { padding: 18px 10px !important; }
      .header { padding: 24px 22px 14px !important; }
      .content { padding: 8px 24px 28px !important; }
      h1 { font-size: 24px !important; }
    }
  </style>
</head>
<body>
  <table role="presentation" width="100%" class="wrapper">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" class="card">
          <tr><td class="brand-line">&nbsp;</td></tr>
          <tr>
            <td class="header">
              <img
                class="logo"
                src="{{ $logoUrl }}"
                alt="{{ $companyName }}"
                width="280"
              >
            </td>
          </tr>
          <tr>
            <td class="content">
              <h1>You're invited to join {{ $householdName }}</h1>
              <p>Hi,</p>
              <p><strong>{{ $inviterName }}</strong> has invited you to join <strong>{{ $householdName }}</strong> on {{ $companyName }}.</p>
              <p>{{ $companyName }} helps families keep everyday life organised in one place — including tasks, renewals and important household information.</p>
              
              <div class="info-box">
                <p style="margin: 0 0 8px; font-weight: 700; color: #10233F;">To accept the invitation:</p>
                <ol class="steps">
                  <li>Download Household OS from the App Store or Google Play.</li>
                  <li>Sign up or log in using this email address.</li>
                  <li>When the invitation appears in the app, tap to accept it.</li>
                </ol>
              </div>

              <div class="app-buttons">
                <a href="{{ $appStoreUrl }}" class="app-link">Download for iPhone</a>
                <a href="{{ $playStoreUrl }}" class="app-link">Download for Android</a>
              </div>

              <p class="muted">After you accept, you may briefly see a confirmation step while the Household Coordinator verifies your membership.</p>
              
              <div class="divider"></div>
              <p class="muted">If you were not expecting this invitation, you can safely ignore this email.</p>
            </td>
          </tr>
        </table>

        <div class="footer">
          {{ $companyName }} — {{ $tagline }}<br>
          {{ $disclaimer }}
        </div>
      </td>
    </tr>
  </table>
</body>
</html>