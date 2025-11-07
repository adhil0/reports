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
        //         label: 'Platform',
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

new DataTable('#lowutilizationandtelco', {
    layout: {
        top1: 'searchPanes',
        topStart: {
            buttons: ['copy', 'csv']
        },
    },
    pageLength: 1000,
    searchPanes: {
        columns: [0, 1, 4],
        hideCount: true,
    },
    columnDefs: [{
        searchPanes: {
            show: true
        },
        targets: [0, 1, 4]
    }]
});

new DataTable('#allmachinesbygroups', {
    layout: {
        top1: 'searchPanes',
    },
    pageLength: 5000,
    searchPanes: {
        columns: [0, 3, 6],
        hideCount: true,
    },
});

new DataTable('#utilizationbymachines', {
    pageLength: 5000,
    layout: {
        top1: 'searchPanes',
    },
    searchPanes: {
        columns: [2], // Group Column
        hideCount: true,
    },
    // Make sure Group column's filter is visible
    columnDefs: [{
        searchPanes: {
            show: true
        },
        targets: [2]  // Group Column
    }]
});

new DataTable('#reservationsbygroups', {
    pageLength: 5000,
    layout: { 
        top1: 'searchPanes',
    },
    searchPanes: {
        columns: [2],  // Group Column
        hideCount: true,
    },

    columnDefs: [{
        searchPanes: {
            show: true
        },
        targets: [2]}]  // Group Column
});


new DataTable('#utilizationbygroups', {
    pageLength: 5000,
    layout: {
        top1: 'searchPanes',
    },
    searchPanes: {
        columns: [0], // Group Column
        hideCount: true,
    },
    // Make sure Group column's filter is visible
    columnDefs: [{
        searchPanes: {
            show: true
        },
        targets: [0]  // Group Column
    }]
});

new DataTable('#nonreservablebygroups', {
    pageLength: 5000,
    layout: { 
        top1: 'searchPanes',
    },
    searchPanes: {
        columns: [2],  // Group Column
        hideCount: true,
    },

    columnDefs: [{
        searchPanes: {
            show: true
        },
        targets: [2]}]  // Group Column
});

new DataTable('#availablebygroups', {
    pageLength: 5000,
    layout: { 
        top1: 'searchPanes',
    },
    searchPanes: {
        columns: [2],  // Group Column
        hideCount: true,
    },

    columnDefs: [{
        searchPanes: {
            show: true
        },
        targets: [2]}]  // Group Column
});
