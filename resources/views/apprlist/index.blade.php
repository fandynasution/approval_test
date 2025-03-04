<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" name="viewport">
        <meta name="viewport" content="width=device-width">

        <title>BTID - Approval Lists</title>

        <link rel="stylesheet" href="assets/css/siqtheme.css">

        <!-- DataTables CSS -->
        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">

        <link rel="shortcut icon" type="image/x-icon" href="{{ url('public/images/KuraKuraBali-iconew.ico') }}">

        <!-- jQuery (Pastikan jQuery disertakan) -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

        <!-- DataTables JS -->
        <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

    </head>
    <body class="theme-dark" style="overflow: auto;" cz-shortcut-listen="true">
        <div class="grid-wrapper sidebar-bg bg1">
            <div class="main">
                <div class='row'>
                    <div class="col-lg-12">
                        <div class="card mb-3">
                            <div class="card-header">
                                <div class="caption uppercase">
                                    Approval List
                                </div>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered table-hover init-datatable dataTable no-footer" id="DataTables_Table_0" role="grid" aria-describedby="DataTables_Table_0_info">
                                    <thead class="thead-light">
                                        <tr role="row">
                                            <th>Entity Code</th>
                                            <th>Document No</th>
                                            <th>User ID</th>
                                            <th>Level No</th>
                                            <th>Status</th>
                                            <th>Sent Mail Date</th>
                                            <th>Module</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            $(document).ready(function() {
                $('#DataTables_Table_0').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: "{{ route('apprlist.getData') }}", // Pastikan route ini sesuai
                    columns: [
                        { data: 'entity_cd', name: 'entity_cd' },
                        { data: 'doc_no', name: 'doc_no' },
                        { data: 'user_id', name: 'user_id' },
                        { data: 'level_no', name: 'level_no' },
                        { data: 'status', name: 'status' },
                        { data: 'sent_mail_date', name: 'sent_mail_date' },
                        {
                            data: null,
                            name: 'cetak_option',
                            orderable: false,
                            searchable: false,
                            render: function(data, type, row) {
                                if (row.module === 'PO' && row.TYPE === 'S') {
                                    return 'Quotation';
                                } else if (row.module === 'PO' && row.TYPE === 'Q') {
                                    return 'Purchase Requisition';
                                } else if (row.module === 'PO' && row.TYPE === 'A') {
                                    return 'Purchase Order';
                                } else if (row.module === 'CB' && row.TYPE === 'D') {
                                    return 'Recapitulation Bank';
                                } else if (row.module === 'CB' && row.TYPE === 'E') {
                                    return 'Propose Transfer';
                                } else if (row.module === 'CB' && row.TYPE === 'G') {
                                    return 'Cash Advance Settlement';
                                } else if (row.module === 'CB' && row.TYPE === 'U') {
                                    return 'Payment Request';
                                } else if (row.module === 'CB' && row.TYPE === 'V') {
                                    return 'Payment Request VVIP';
                                } else if (row.module === 'CM' && row.TYPE === 'A') {
                                    return 'Contract Progress';
                                } else if (row.module === 'CM' && row.TYPE === 'B') {
                                    return 'Contract Complete';
                                } else if (row.module === 'CM' && row.TYPE === 'C') {
                                    return 'Warranty Complete';
                                } else if (row.module === 'CM' && row.TYPE === 'D') {
                                    return 'Varian Order';
                                } else if (row.module === 'CM' && row.TYPE === 'E') {
                                    return 'Contract Entry';
                                } else if (row.module === 'PL' && row.TYPE === 'Y') {
                                    return 'PL Budget';
                                } else if (row.module === 'TM' && row.TYPE === 'R') {
                                    return 'Contract Renew';
                                } else {
                                    return ''; // Bisa dikosongkan atau diisi dengan teks lain
                                }
                            }
                        },
                        {
                            data: null,
                            name: 'action',
                            orderable: false,
                            searchable: false,
                            render: function(data, type, row) {
                                return `<button class="btn btn-primary send-data" 
                                            data-entity_cd="${row.entity_cd}" 
                                            data-doc_no="${row.doc_no}" 
                                            data-user_id="${row.user_id}">
                                            Re-Send Email
                                        </button>`;
                            }
                        }
                    ]
                });

                $('#DataTables_Table_0').on('click', '.send-data', function() {
                    let entity_cd = $(this).data('entity_cd');
                    let doc_no = $(this).data('doc_no');
                    let user_id = $(this).data('user_id');

                    $.ajax({
                        url: "{{ route('apprlist.sendData') }}", // Pastikan route ini sesuai
                        type: "POST",
                        data: {
                            _token: "{{ csrf_token() }}", // Diperlukan untuk Laravel
                            entity_cd: entity_cd,
                            doc_no: doc_no,
                            user_id: user_id
                        },
                        success: function(response) {
                            console.log(response); // Debugging di console browser
                            // alert("Hasil Query:\n" + JSON.stringify(response, null, 2)); // Tampilkan hasil query dalam alert
                            alert("OK");
                        },
                        error: function(xhr, status, error) {
                            alert("Terjadi kesalahan: " + xhr.responseText);
                        }
                    });
                });
            });
        </script>
    </body>
</html>