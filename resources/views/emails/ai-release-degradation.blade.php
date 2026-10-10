<p>An AI extraction profile release needs manual review.</p>

<p><strong>Profile:</strong> {{ $profileName }} {{ $profileVersion }}</p>

<ul>
    @foreach ($reasons as $reason)
        <li>{{ $reason }}</li>
    @endforeach
</ul>

<p><a href="{{ route('social.ai-profile-releases.show', $releaseUuid) }}">Review aggregate release metrics</a></p>

<p>Any rollback must be performed by an administrator from AI Profiles.</p>
