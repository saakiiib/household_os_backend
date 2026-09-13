@php
  $companyName = \App\Models\Setting::get('company_name', 'Household OS');
  $logo = \App\Models\Setting::get('logo');
  $logoUrl = $logo ? asset($logo) : asset('logo.png');
  $tagline = \App\Models\Setting::get('footer_tagline', 'The operating system for modern family life.');
  $disclaimer = \App\Models\Setting::get('footer_disclaimer', 'This is an automated service email. Please do not share verification or reset codes with anyone.');
@endphp
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="x-apple-disable-message-reformatting">
  <title>Subscription reminder - {{ $companyName }}</title>
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
    .button-wrap { padding: 8px 0 22px; text-align: center; }
    .button {
      display: inline-block;
      padding: 14px 24px;
      border-radius: 10px;
      background: #087DE3;
      color: #FFFFFF !important;
      text-decoration: none;
      font-size: 15px;
      line-height: 1;
      font-weight: 700;
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
              <h1>Subscription reminder</h1>
              <p>Hi {{ $notifiable->first_name }},</p>
              <p>{{ $message }}</p>
              
              <div class="button-wrap">
                <a href="{{ url('/subscription') }}" class="button">View Subscription</a>
              </div>

              <p class="muted">Your {{ $companyName }} plan applies to your household. You can review your plan, renewal details and available options from the Subscription section in the app.</p>
              
              <div class="divider"></div>
              <p class="muted">If you have already updated your subscription, no further action may be needed.</p>
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