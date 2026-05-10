<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $monthLabel }} summary · {{ $group->name }}</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:'Segoe UI',Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;">

@php
  $netBalance  = $stats['netBalance'];
  $groupTotal  = $stats['groupTotal'];
  $paidByMember = $stats['paidByMember'];
  $memberShare  = $stats['memberShare'];
  $categories   = $stats['categories'];
  $recent       = $stats['recent'];
  $expenseCount = $stats['expenseCount'];
  $netPositive  = $netBalance >= 0;
@endphp

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f8fafc;padding:40px 16px;">
<tr><td align="center">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:520px;background:#ffffff;border-radius:24px;overflow:hidden;box-shadow:0 4px 32px rgba(99,102,241,0.10);">

  {{-- Header --}}
  <tr>
    <td style="background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);padding:36px 36px 28px;text-align:center;">
      <div style="display:inline-block;width:52px;height:52px;background:rgba(255,255,255,0.18);border-radius:16px;line-height:52px;text-align:center;margin-bottom:14px;font-size:26px;">📊</div>
      <h1 style="margin:0;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.3px;">{{ $monthLabel }} Summary</h1>
      <p style="margin:6px 0 0;color:rgba(255,255,255,0.75);font-size:13px;">{{ $group->name }} · {{ $expenseCount }} expense{{ $expenseCount !== 1 ? 's' : '' }}</p>
    </td>
  </tr>

  {{-- Body --}}
  <tr>
    <td style="padding:28px 32px 24px;">

      {{-- Net balance highlight --}}
      <div style="background:{{ $netPositive ? '#f0fdf4' : '#fef2f2' }};border:1px solid {{ $netPositive ? '#bbf7d0' : '#fecaca' }};border-radius:16px;padding:18px 22px;margin-bottom:24px;text-align:center;">
        <p style="margin:0 0 4px;color:{{ $netPositive ? '#16a34a' : '#dc2626' }};font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;">
          {{ $netPositive ? 'You are owed' : 'You owe' }}
        </p>
        <p style="margin:0;color:{{ $netPositive ? '#15803d' : '#b91c1c' }};font-size:28px;font-weight:800;letter-spacing:-0.5px;">
          RS&nbsp;{{ number_format(abs($netBalance), 0) }}
        </p>
      </div>

      {{-- Stats grid --}}
      <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
        <tr>
          <td style="width:33%;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;text-align:center;vertical-align:top;">
            <p style="margin:0 0 4px;color:#94a3b8;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.07em;">Group total</p>
            <p style="margin:0;color:#0f172a;font-size:15px;font-weight:800;">RS&nbsp;{{ number_format($groupTotal, 0) }}</p>
          </td>
          <td style="width:4px;"></td>
          <td style="width:33%;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;text-align:center;vertical-align:top;">
            <p style="margin:0 0 4px;color:#94a3b8;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.07em;">You paid</p>
            <p style="margin:0;color:#6366f1;font-size:15px;font-weight:800;">RS&nbsp;{{ number_format($paidByMember, 0) }}</p>
          </td>
          <td style="width:4px;"></td>
          <td style="width:33%;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;text-align:center;vertical-align:top;">
            <p style="margin:0 0 4px;color:#94a3b8;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.07em;">Your share</p>
            <p style="margin:0;color:#0f172a;font-size:15px;font-weight:800;">RS&nbsp;{{ number_format($memberShare, 0) }}</p>
          </td>
        </tr>
      </table>

      {{-- Top categories --}}
      @if($categories->count() > 0)
        <p style="margin:0 0 10px;color:#94a3b8;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.07em;">Top categories</p>
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;margin-bottom:24px;">
          @foreach($categories as $i => $cat)
            @php $catName = ucfirst(str_replace('-', ' ', $cat->category ?? 'General')); @endphp
            <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 18px;{{ !$loop->last ? 'border-bottom:1px solid #e2e8f0;' : '' }}">
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="color:#334155;font-size:13px;font-weight:600;">{{ $catName }}</td>
                  <td style="text-align:right;">
                    <span style="color:#94a3b8;font-size:11px;font-weight:500;margin-right:10px;">{{ $cat->cnt }} expense{{ $cat->cnt != 1 ? 's' : '' }}</span>
                    <span style="color:#0f172a;font-size:13px;font-weight:700;">RS&nbsp;{{ number_format($cat->total, 0) }}</span>
                  </td>
                </tr>
              </table>
            </div>
          @endforeach
        </div>
      @endif

      {{-- Recent expenses --}}
      @if($recent->count() > 0)
        <p style="margin:0 0 10px;color:#94a3b8;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.07em;">Recent expenses</p>
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;margin-bottom:24px;">
          @foreach($recent as $expense)
            <div style="{{ !$loop->last ? 'border-bottom:1px solid #e2e8f0;' : '' }}padding:11px 18px;">
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="vertical-align:top;">
                    <p style="margin:0;color:#0f172a;font-size:13px;font-weight:600;">{{ $expense->description }}</p>
                    <p style="margin:2px 0 0;color:#94a3b8;font-size:11px;">{{ $expense->payer->name }} · {{ $expense->date->format('M d') }}</p>
                  </td>
                  <td style="text-align:right;vertical-align:top;">
                    <p style="margin:0;color:#0f172a;font-size:13px;font-weight:700;">RS&nbsp;{{ number_format($expense->amount, 0) }}</p>
                  </td>
                </tr>
              </table>
            </div>
          @endforeach
        </div>
      @endif

      {{-- CTA --}}
      <div style="text-align:center;">
        <a href="{{ config('app.url') }}/dashboard"
           style="display:inline-block;background:#6366f1;color:#ffffff;padding:13px 32px;border-radius:12px;font-size:14px;font-weight:700;text-decoration:none;letter-spacing:0.01em;">
          Open Taufani →
        </a>
      </div>

    </td>
  </tr>

  {{-- Footer --}}
  <tr>
    <td style="padding:18px 32px 24px;border-top:1px solid #f1f5f9;text-align:center;">
      <p style="margin:0;color:#94a3b8;font-size:12px;line-height:1.6;">
        Monthly summary for <strong>{{ $group->name }}</strong> — {{ $monthLabel }}<br>
        Sent by <a href="{{ config('app.url') }}" style="color:#6366f1;text-decoration:none;">Taufani</a>
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>

</body>
</html>
