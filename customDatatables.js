DataTable.ext.errMode = 'throw'; // Default is 'alert', but alerts aren't working with GLPI for some reason...
new DataTable('#datatable', {
    layout: {
        top1: 'searchPanes',
    },
    pageLength: 1000,
    searchPanes: {
        columns: [0],
        hideCount: true,
        panes: [{
            header: 'Custom Search',
            options: [{
                label: 'RAN',
                value: function (rowData, rowIdx) {
                    return rowData[0] == 'Telco &gt; Platform' || rowData[0] == 'Telco &gt; Platform &gt; Core' || rowData[0] == 'Telco &gt; Platform &gt; Far Edge SNO' || rowData[0] == 'Telco &gt; Platform &gt; Hypervisors' || rowData[0] == 'Telco &gt; Platform &gt; INFRA' || rowData[0] == 'Telco &gt; Platform &gt; PlatformShared' || rowData[0] == 'Telco &gt; Platform &gt; RANCI' || rowData[0] == 'Telco &gt; Platform &gt; Specific Projects' || rowData[0] == 'Telco &gt; Platform &gt; telco5gci' || rowData[0] == 'Telco &gt; Platform &gt; Timing' || rowData[0] == 'Telco &gt; Workload' || rowData[0] == 'Telco &gt; Workload &gt; Cert Tooling' || rowData[0] == 'Telco &gt; Workload &gt; certification-pool' || rowData[0] == 'Telco &gt; Workload &gt; Infra';
                }
            }]
        }]
    },
    columnDefs: [{
        searchPanes: {
            show: true
        },
        targets: [0]
    }]
});
