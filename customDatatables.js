DataTable.ext.errMode = 'throw'; // Default is 'alert', but alerts aren't working with GLPI for some reason...
new DataTable('#datatable', {
    layout: {
        top1: 'searchPanes',
    },
    pageLength: 1000,
    searchPanes: {
        columns: [0],
        hideCount: true,
        // panes: [{
        //     header: 'Custom Search',
        //     options: [{
        //         label: 'RAN',
        //         value: function (rowData, rowIdx) {
        //             return rowData[0] == <INSERT GROUPS HERE>;
        //         }
        //     }]
        // }]
    },
    columnDefs: [{
        searchPanes: {
            show: true
        },
        targets: [0]
    }]
});
