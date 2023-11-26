<li><a class="{{ menuActive('user.home') }}" href="{{ route('user.home') }}">@lang('Dashboard')</a></li>

@if ($general->modules->deposit)
    <li> <a class="{{ menuActive('user.deposit*') }}" href="{{ route('user.deposit.history') }}">@lang('Deposit')</a></li>
@endif

@if ($general->modules->withdraw)
    <li><a class="{{ menuActive('user.withdraw*') }}" href="{{ route('user.withdraw.history') }}">@lang('Withdraw')</a></li>
@endif

    <li><a class="{{ menuActive('user.loan*') }}" href="{{ route('user.loan.plans') }}">@lang('Loan')</a></li>




<li>
    <a class="{{ menuActive(['user.profile.setting', 'user.twofactor', 'user.change.password', 'user.transaction.history', 'ticket', 'ticket.open', 'ticket.view']) }}" href="{{ route('user.profile.setting') }}">@lang('More')</a>
</li>
