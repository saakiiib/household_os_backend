<div class="modal fade flip" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body p-5 text-center">
                <lord-icon src="https://cdn.lordicon.com/gsqxdxog.json"
                    trigger="loop"
                    colors="primary:#405189,secondary:#f06548"
                    style="width:90px;height:90px">
                </lord-icon>

                <div class="mt-4 text-center">
                    <h4 id="deleteModalTitle">Are you sure?</h4>
                    <p class="text-muted fs-15 mb-4" id="deleteModalMessage">
                        Deleting this item will remove all related data.
                    </p>
                    <div class="hstack gap-2 justify-content-center">
                        <button class="btn btn-link link-success fw-medium text-decoration-none"
                                data-bs-dismiss="modal">
                            <i class="ri-close-line me-1 align-middle"></i> Close
                        </button>
                        <button class="btn btn-danger" id="confirmDeleteBtn">Yes, Delete It</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>