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
<script src="https://cdn.datatables.net/buttons/1.0.3/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>


<!-- ✅ Other Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.2.0/tinymce.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- DataTables JS and Bootstrap Integration -->


<!-- REQUIRED: JSZip BEFORE buttons.html5 -->



<!-- HTML5 + Print buttons -->
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="/vendor/datatables/buttons.server-side.js"></script>
<!-- ✅ Custom JS Last -->\
 
<script src="{{ asset('js/custom.js') }}"></script>

@stack('scripts')
