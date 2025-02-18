<!-- ✅ Load jQuery First -->

<!-- ✅ Load Select2 CSS Early -->

<!-- ✅ Load Select2 JS Immediately After jQuery -->

<!-- ✅ Load Other Plugins AFTER jQuery & Select2 -->
<script src="{{ asset('assets/plugins/global/plugins.bundle.js') }}"></script>
<script src="{{ asset('assets/js/scripts.bundle.js') }}"></script>
<script src="{{ asset('assets/js/widgets.bundle.js') }}"></script>
<script src="{{ asset('assets/js/custom/widgets.js') }}"></script>

<!-- ✅ DataTables Scripts -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>

<!-- ✅ Other Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.2.0/tinymce.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- DataTables JS and Bootstrap Integration -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>


<!-- ✅ Custom JS Last -->
<script src="{{ asset('js/custom.js') }}"></script>

@stack('scripts')
