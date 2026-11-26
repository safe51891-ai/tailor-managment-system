@props(['route', 'title'])
@php
    $archiving = $route === 'staff.destroy';
    $action = 'Delete';
    $recordType = $archiving ? 'employee' : strtolower($title);
@endphp
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" role="dialog" aria-labelledby="delete-confirm-title" aria-describedby="delete-confirm-message" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route($route) }}" method="post" id="delete-confirm-form">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h5 class="modal-title" id="delete-confirm-title">Confirm {{ strtolower($action) }}</h5>
                    <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close confirmation"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="delete_id">
                    <p id="delete-confirm-message">Are you sure you want to {{ strtolower($action) }} this {{ $recordType }}?</p>
                    <p class="measurement-help"><strong id="delete-record-label"></strong></p>
                    <p class="text-muted">{{ $archiving ? 'The employee will leave the active team list. Their work and pay history will be kept.' : 'This record will be removed. Check the selected record before confirming.' }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="delete-confirm-cancel" data-dismiss="modal">Cancel</button>
                    <button class="btn btn-danger" id="delete-confirm-submit" type="submit" disabled>Yes, {{ strtolower($action) }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
