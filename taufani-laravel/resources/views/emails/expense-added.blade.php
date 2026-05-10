<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>New expense · {{ $expense->group->name }}</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:'Segoe UI',Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;">

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f8fafc;padding:40px 16px;">
<tr><td align="center">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:520px;background:#ffffff;border-radius:24px;overflow:hidden;box-shadow:0 4px 32px rgba(99,102,241,0.10);">

  {{-- Header --}}
  <tr>
    <td style="background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);padding:36px 36px 28px;text-align:center;">
      <div style="display:inline-block;width:52px;height:52px;background:rgba(255,255,255,0.18);border-radius:16px;line-height:52px;text-align:center;margin-bottom:14px;font-size:26px;">🧾</div>
      <h1 style="margin:0;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.3px;">New expense added</h1>
      <p style="margin:6px 0 0;color:rgba(255,255,255,0.75);font-size:13px;">{{ $expense->group->name }}</p>
    </td>
  </tr>

  {{-- Body --}}
  <tr>
    <td style="padding:28px 32px 24px;">

      {{-- Expense card --}}
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:20px 22px;margin-bottom:24px;">
        <p style="margin:0 0 4px;color:#94a3b8;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.07em;">Expense</p>
        <p style="margin:0 0 18px;color:#0f172a;font-size:17px;font-weight:700;">{{ $expense->description }}</p>

        <table width="100%" cellpadding="0" cellspacing="0">
          <tr>
            <td style="width:50%;vertical-align:top;">
              <p style="margin:0;color:#94a3b8;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.07em;">Paid by</p>
              <p style="margin:4px 0 0;color:#0f172a;font-size:14px;font-weight:600;">{{ $expense->payer->name }}</p>
              <p style="margin:2px 0 0;color:#94a3b8;font-size:12px;">{{ $expense->date->format('M d, Y') }}</p>
            </td>
            <td style="width:50%;vertical-align:top;text-align:right;">
              <p style="margin:0;color:#94a3b8;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.07em;">Total</p>
              <p style="margin:4px 0 0;color:#6366f1;font-size:24px;font-weight:800;letter-spacing:-0.5px;">RS&nbsp;{{ number_format($expense->amount, 0) }}</p>
            </td>
          </tr>
        </table>

        @if($recipientShare > 0)
          <div style="margin-top:16px;padding-top:16px;border-top:1px solid #e2e8f0;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td style="color:#64748b;font-size:13px;font-weight:600;">Your share</td>
                <td style="text-align:right;color:#0f172a;font-size:15px;font-weight:800;">RS&nbsp;{{ number_format($recipientShare, 0) }}</td>
              </tr>
            </table>
          </div>
        @endif
      </div>

      {{-- Participants --}}
      @if($expense->participants->count() > 0)
        <p style="margin:0 0 10px;color:#94a3b8;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.07em;">Split among</p>
        <p style="margin:0 0 24px;color:#475569;font-size:13px;font-weight:500;">
          {{ $expense->participants->pluck('name')->join(', ') }}
        </p>
      @endif

      {{-- CTA --}}
      <div style="text-align:center;margin-top:8px;">
        <a href="{{ config('app.url') }}/dashboard"
           style="display:inline-block;background:#6366f1;color:#ffffff;padding:13px 32px;border-radius:12px;font-size:14px;font-weight:700;text-decoration:none;letter-spacing:0.01em;">
          View in Taufani →
        </a>
      </div>

    </td>
  </tr>

  {{-- Footer --}}
  <tr>
    <td style="padding:18px 32px 24px;border-top:1px solid #f1f5f9;text-align:center;">
      <p style="margin:0;color:#94a3b8;font-size:12px;line-height:1.6;">
        You're receiving this because you're a member of <strong>{{ $expense->group->name }}</strong>.<br>
        Sent by <a href="{{ config('app.url') }}" style="color:#6366f1;text-decoration:none;">Taufani</a>
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>

</body>
</html>
