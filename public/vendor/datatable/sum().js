/**
 * Fairly simply, this plug-in will take the data from an API result set
 * and sum it, returning the summed value. The data can come from any data
 * source, including column data, cells or rows.
 *
 * Note that it will attempt to 'deformat' any string based data that is passed
 * into it - i.e. it will strip any non-numeric characters in order to make a
 * best effort attempt to sum all data types. This can be useful when working
 * with formatting numbers such as currency. However the trade-off is that no
 * error is thrown if non-numeric data is passed in. You should be aware of this
 * in case unexpected values are returned - likely the input data is not what is
 * expected.
 *
 *  @name sum()
 *  @summary Sum the values in a data set.
 *  @author [Allan Jardine](http://sprymedia.co.uk)
 *  @requires DataTables 1.10+
 *
 *  @returns {Number} Summed value
 *
 *  @example
 *    // Simply get the sum of a column
 *    var table = $('#example').DataTable();
 *    table.column( 3 ).data().sum();
 *
 *  @example
 *    // Insert the sum of a column into the columns footer, for the visible
 *    // data on each draw
 *    $('#example').DataTable( {
 *      drawCallback: function () {
 *        var api = this.api();
 *        $( api.table().footer() ).html(
 *          api.column( 4, {page:'current'} ).data().sum()
 *        );
 *      }
 *    } );
 */

// Deformat a value that may be a plain number or an Indonesian-formatted
// money string (dot = thousands separator, comma = decimal separator, e.g.
// "1.500.000,00"). The previous implementation only stripped non-numeric
// characters and cast with `* 1`, which meant JS read the thousands dot as
// a decimal point (e.g. "1.500" became 1.5), silently dropping the last
// three digits from every footer total that used this plug-in.
function limsDeformatNumber( v ) {
	if ( typeof v !== 'string' ) {
		return v;
	}
	v = v.replace(/[^\d,.\-]/g, '').trim();
	if ( !v ) {
		return 0;
	}
	if ( v.indexOf(',') !== -1 ) {
		// Comma present => Indonesian format, comma is the decimal separator.
		v = v.replace(/\./g, '').replace(',', '.');
	}
	else if ( (v.match(/\./g) || []).length > 1 || /^\d{1,3}(\.\d{3})+$/.test(v) ) {
		// Multiple dots, or a single dot grouping digits in 3s => thousands separator.
		v = v.replace(/\./g, '');
	}
	var n = parseFloat(v);
	return isNaN(n) ? 0 : n;
}

jQuery.fn.dataTable.Api.register( 'sum()', function ( ) {
	return this.flatten().reduce( function ( a, b ) {
		a = limsDeformatNumber(a);
		b = limsDeformatNumber(b);

		return a + b;
	}, 0 );
} );

