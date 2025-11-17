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
        columns: [2, 3, 6],
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


  /**
   * updateOrder() retrieves the current column order settings
   * and updates the URL to reflect these settings
   */
  function updateOrder () {
    const order = JSON.stringify(table.order())
    if (order.length > 0) {
      if (query.includes('order=')) {
        // Update order section of query in place
        query = query.replace(/order=.[^&]*/i, 'order=' + order)
      } else {
        if (query.length > 1) {
          query = query + '&'
        }
        query = query + 'order=' + order
      }
    } else {
      if (query.includes('order')) {
        // Remove order section from query
        query = query.replace(/&?order=.[^&]*/i, '')
      }
    }
    history.replaceState(null, null, query)
  }

  /**
   * updateSearch() retrieves the current search settings
   * and updates the URL to reflect these settings
   */
  function updateSearch () {
    const search = table.search()
    if (search.length > 0) {
      if (query.includes('search.search=')) {
        // Update search section of query in place
        query = query.replace(
          /search.search=.[^&]*/i,
          'search.search=' + search
        )
      } else {
        if (query.length > 1) {
          query = query + '&'
        }
        query = query + 'search.search=' + search
      }
    } else {
      if (query.includes('search.search')) {
        // Remove search section from query
        query = query.replace(/&?search.search=.[^&]*/i, '')
      }
    }
    history.replaceState(null, null, query)
  }

  /**
   * updatePanes() retrieves the current search panes settings
   * and updates the URL to reflect these settings
   */
  function updatePanes () {
    const panes = []
    setTimeout(function () {
      // Retrieve active filters
      $.each($('div.dtsp-searchPane'), function (i, col) {
        const colName = $(col).find('input').attr('placeholder')
        let colIndex = table.column(':contains(' + colName + ')').index()
        if (colIndex === undefined) {
          // If more custom search panes are added, this section needs to be changed
          colIndex = extraCol + 1
        }
        const column = { column: colIndex, rows: [] }
        const rows = []
        $.each($('tr.selected', col), function (j, row) {
          rows.push($('span:eq(0)', row).text())
        })
        if (rows.length !== 0) {
          column.rows = rows
          panes.push(column)
        }
      })

      // Update URL with active filters
      if (panes.length > 0) {
        if (query.includes('searchPanes.preSelect')) {
          // Update search panes section of query in place
          query = query.replace(
            /searchPanes.preSelect=.[^&]*/i,
            'searchPanes.preSelect=' + JSON.stringify(panes)
          )
        } else {
          if (query.length > 1) {
            query = query + '&'
          }
          query = query + 'searchPanes.preSelect=' + JSON.stringify(panes)
        }
      } else {
        if (query.includes('searchPanes.preSelect')) {
          // Remove search panes section from query
          query = query.replace(/&?searchPanes.preSelect=.[^&]*/i, '')
        }
      }
      history.replaceState(null, null, query)
    }, 1)
  }

  // Make sure previous URL settings are not overwritten
  const url = window.location.href
  let query = ''
  if (url.includes('?')) {
    const deeplink = url.slice(url.indexOf('?'))
    if (deeplink.length > 1) {
      query = deeplink
    }
  } else {
    query = '?'
  }

  const extraCol = table.columns()[0].length - 1

  /**
   * Update order and search pane settings when datatable is reordered,
   * and update search settings when a search event occurs
   */
  $('#data').on('order.dt', updateOrder)
  $('#data').on('order.dt', updatePanes)
  $('#data').on('search.dt', updateSearch)
})


// in document ready function
$(document).ready(function() {
  // Deeplinking: https://datatables.net/blog/2017-07-24#Usage
  const deeplinkList = [
    'search.search',
    'order',
    'displayStart',
    'searchPanes.preSelect'
  ]
  const searchOptions = $.fn.dataTable.ext.deepLink(deeplinkList)
});