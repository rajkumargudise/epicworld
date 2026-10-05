@extends('layouts.public')

@php($context = 'page')

@section('content')
    <article class="mx-auto max-w-3xl">
        <p class="text-xs font-semibold uppercase tracking-wider text-accent">Legal</p>
        <h1 class="mt-2 text-4xl font-extrabold tracking-tight sm:text-5xl">Terms of use</h1>
        <p class="mt-2 text-sm text-muted">Last updated {{ now()->format('j F Y') }}</p>
        <div class="article-body mt-8">
            <p>By using EPIC World you agree to these terms. If you do not agree, please do not use the site.</p>

            <h2>Using the site</h2>
            <p>You may read and share our content for personal, non-commercial use with credit and a link back. You may not scrape the site at scale, attempt to disrupt it, or try to access areas you are not authorised to use.</p>

            <h2>Accounts and submitted posts</h2>
            <ul>
                <li>You must give accurate details and keep your password secure. You are responsible for activity on your account.</li>
                <li>Posts you submit must be your own original work, must not infringe anyone's rights, and must not be defamatory, hateful, misleading or unlawful.</li>
                <li>Submitting a post does not guarantee publication. Editors may edit, decline or remove any post at any time.</li>
                <li>You keep ownership of your work. By submitting it you give EPIC World a non-exclusive licence to publish, display and promote it on the site and its channels.</li>
                <li>We may suspend accounts that break these terms.</li>
            </ul>

            <h2>Comments</h2>
            <p>Comments are moderated and may be edited or removed. Do not post spam, personal information, harassment or unlawful material. You are responsible for what you write.</p>

            <h2>Third-party content and links</h2>
            <p>The live desk and some pages link to or embed content from other publishers and platforms (for example news sites and YouTube). That content belongs to its owners and is governed by their terms. We are not responsible for it.</p>

            <h2>No professional advice</h2>
            <p>Content is provided for general information. It is not legal, medical, financial or other professional advice.</p>

            <h2>Liability</h2>
            <p>We work hard to be accurate, but the site is provided &ldquo;as is&rdquo; without warranties, and to the extent permitted by law we are not liable for losses arising from your use of it.</p>

            <h2>Changes and contact</h2>
            <p>We may update these terms and will change the date above when we do. Questions? <a href="{{ route('contact') }}">Contact us</a>. See also our <a href="{{ route('privacy') }}">Privacy policy</a>.</p>
        </div>
    </article>
@endsection
