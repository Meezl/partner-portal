{{--
    Repeats on every page. Pass the conference `year` for the hashtag, and an
    optional `note` (e.g. a document reference) shown at the left.
--}}
<div class="footer-band">
    <div class="web">
        @isset($note)<span class="note">{{ $note }}</span>@endisset
        <span>www.ahaic.org</span>
    </div>
    <div class="tag">#AHAIC{{ $year ?? '' }}</div>
</div>
