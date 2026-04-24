<?php
/*
Plugin Name: Stock Calculator
Description: Connects WordPress to Google Sheets, supports safe concurrent usage (sandbox per user).
Version: 1.0
Author: Manoj Kumar
*/
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/vendor/autoload.php'; // Google API Library auto-loader

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Sheets\BatchUpdateValuesRequest;
use Google\Service\Sheets\ValueRange;
/**
 * Main Function
 */
add_action('wp_ajax_nopriv_run_stock_sheet_process', 'run_stock_sheet_process');
add_action('wp_ajax_run_stock_sheet_process', 'run_stock_sheet_process');

function run_stock_sheet_process() {
   // echo "<pre>"; print_r($_POST); die('ddddddd');
    $portfolio = $_POST['portfolio'];
    $currency = $_POST['currency'];
    $amount = $_POST['amount'];
    $stocks = $_POST['stocks'];
    $stocks_val = explode(',', $stocks);
  
    // ➤ REQUIRED: CHANGE THIS
    if( $portfolio == 'COMPASS'){
        $sourceSheetId = "1QsDYh3CaB-m3ehOovu_dXBb8kBfR-TsywHfNLVzaAmk"; //Google spreadsheet ID
        $CalculatorSheetId = '1075580578'; //Id of sheet which should be copied 
    }elseif( $portfolio == 'ASCENT'){
        $sourceSheetId = "1RFMxbpIuo2qfYPwaG9v93IwyMf2cwsxSRnwJzfw_ydA"; //Google spreadsheet ID
        $CalculatorSheetId = '1075580578'; //Id of sheet which should be copied 
      
    }
    elseif( $portfolio == 'ANCHOR'){
        $sourceSheetId = "1qDXmKxii3QRPX-9w1qGJTOwiPrlrU03vpHITB69aDqA"; //Google spreadsheet ID
        $CalculatorSheetId = '1075580578'; //Id of sheet which should be copied 
    }
    
    try {

        // ✅ Google API Client Setup
        $client = new Client();
        $client->setApplicationName('Stock Calculator');
        $client->setScopes([
            Sheets::SPREADSHEETS,
            Drive::DRIVE
        ]);
        $client->setAuthConfig(__DIR__ . '/credentials.json');
        $client->setAccessType('offline');

        $sheetsService = new Sheets($client);
        $driveService  = new Drive($client);

        if (empty($sourceSheetId)) {
            return "❌ ERROR: No sheet ID configured.";
        }

        

       // ✅ Duplicate sheet WITHOUT using Drive storage quota
        $sheetInfo = $sheetsService->spreadsheets->get($sourceSheetId);
      //  $firstSheetId = $sheetInfo->sheets[0]->properties->sheetId; // sheet tab ID
        
        $copyRequest = new Google\Service\Sheets\CopySheetToAnotherSpreadsheetRequest([
            'destinationSpreadsheetId' => $sourceSheetId // duplicate inside same file 
        ]);
		
        $newSheet = $sheetsService->spreadsheets_sheets->copyTo(
            $sourceSheetId,
            $CalculatorSheetId,
            $copyRequest
        );
       
        // The new temporary sheet ID
         $tempSheetId = $newSheet->sheetId;
         $tempSheetName = $newSheet->title;
        // ✅ Read Data (Example: A1:D20)
       /* $range = $tempSheetName.'!C4:J10';
        $rows = $sheetsService->spreadsheets_values->get($sourceSheetId, $range)->getValues();*/
        //echo "<pre>"; print_r($rows);
        
        
        // ✅ Update Data (Example: Writes timestamp into A60)
        $data = [
            new ValueRange([
               'range' => $tempSheetName.'!C38',
                'values' => [[ $currency ]],
            ]),
            new ValueRange([
               'range' => $tempSheetName.'!F38',
                'values' => [[ $amount ]],
            ]),
          /*  new ValueRange([
                'range'  => $tempSheetName . "!B5:C5",
                'values' => [["Value B5", "Value C5"]], // update B5 and C5
            ]),
            new ValueRange([
                'range'  => $tempSheetName . "!D10:F12",
                'values' => [
                    ["Row1 Col1", "Row1 Col2", "Row1 Col3"],
                    ["Row2 Col1", "Row2 Col2", "Row2 Col3"],
                    ["Row3 Col1", "Row3 Col2", "Row3 Col3"],
                ],
            ]),*/
        ];
        
        $batchBody = new BatchUpdateValuesRequest([
            'valueInputOption' => 'USER_ENTERED',
            'data' => $data,
        ]);
        
        //check for any error 
        try {
            $response = $sheetsService->spreadsheets_values->batchUpdate(
                $sourceSheetId,
                $batchBody
            );
        
            error_log("✅ Batch update OK");
            error_log(print_r($response, true));
        
        } catch (Exception $e) {
            error_log("❌ Batch update FAILED: " . $e->getMessage());
        }

        //Updated YES/NO in the sheet
        
        // Your symbols that should stay "Yes"
        $allowedSymbols = array_map('trim', $stocks_val);
        
        // Read column D (symbols)
        $symbolsResponse = $sheetsService->spreadsheets_values->get(
            $sourceSheetId,
            "$tempSheetName!D4:D33"
        );
        $symbols = $symbolsResponse->getValues();
        
        $updateData = [];
        $rowStart = 4;
        
        foreach ($symbols as $index => $row) {
            $symbol = trim($row[0] ?? '');
        
            // Default set to No
            $valueToWrite = "No";
        
            // If symbol is in your allowed list → Yes
            if (in_array($symbol, $allowedSymbols)) {
                $valueToWrite = "Yes";
            }
        
            // Prepare update row for column H
            $updateData[] = [
                "range"  => "$tempSheetName!H" . ($rowStart + $index),
                "values" => [[$valueToWrite]]
            ];
        }
        
        // Batch update request
        $updateBody = new Sheets\BatchUpdateValuesRequest([
            "valueInputOption" => "USER_ENTERED",
            "data" => $updateData
        ]);
        
        $sheetsService->spreadsheets_values->batchUpdate(
            $sourceSheetId,
            $updateBody
        );
        
       // echo "✅ Updated Yes/No based on filtered symbols";


        //Now read updated sheet data
        
         // ✅ Read Data (Example: A1:D20)
		 sleep(2); 
        $range = $tempSheetName.'!C4:K33';
        $range1 = $tempSheetName.'!H36:K39';
        $finalStockData = $sheetsService->spreadsheets_values->get($sourceSheetId, $range)->getValues();
        $cashData = $sheetsService->spreadsheets_values->get($sourceSheetId, $range1)->getValues();
        
        //get array of selected stocks only
        /*$array1 = array_map('trim', $stocks_val);
        
        $finalStockData = array_filter($rows, function ($row) use ($array1) {
            return isset($row[1]) && in_array(trim($row[1]), $array1, true);
        });
        
        $finalStockData = array_values($finalStockData); // re-index result*/
  
       
		$myData['finalStockData'] = $finalStockData;
		$myData['cashData'] = $cashData;




        // ✅ CLEANUP (delete temp sheet + folder)

       $deleteRequest = new Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
        'requests' => [
                [
                    'deleteSheet' => [
                        'sheetId' => (int)$tempSheetId
                    ]
                ]
            ]
        ]);
        
        try {
            $sheetsService->spreadsheets->batchUpdate($sourceSheetId, $deleteRequest);
            error_log("Temp sheet tab deleted.");
        } catch (Exception $e) {
            error_log("Failed to delete temp sheet tab: " . $e->getMessage());
        }
       
        //echo "<pre>"; print_r($myData); die('hhh');

       // return "<pre>" . print_r($rows, true) . "</pre>";
        $jsonString = json_encode($myData);

        // Output the JSON string
        echo $jsonString; exit;
    } catch (Exception $e) {
        return "❌ ERROR: " . $e->getMessage();
    }
}


/**
 * ✅ Register Shortcode
 
function shortcode_stock_calculator() {
    return run_stock_sheet_process();
}

add_shortcode("stock_calculator", "shortcode_stock_calculator");

*/






/* calculatr form */
function stock_calculator_form(){
    ob_start();
            // === CONFIG SECTION COMPASS SHEET===
$compasSpreadsheetId = "1QsDYh3CaB-m3ehOovu_dXBb8kBfR-TsywHfNLVzaAmk";  // <-- Replace this
$compasPortRange = "Model Portfolio!C4:L20";                // <-- Range or full sheet: Sheet1
$compasCurrenciesRange = "Curreny Rates!C4:H150"; 

// Fetch data
$compassModelPortfolio = getStocksCurrency($compasSpreadsheetId, $compasPortRange);
$compassCurrencies = getStocksCurrency($compasSpreadsheetId, $compasCurrenciesRange);

            // === CONFIG SECTION ASCENT SHEET===
/*$ascentSpreadsheetId = "1RFMxbpIuo2qfYPwaG9v93IwyMf2cwsxSRnwJzfw_ydA";  // <-- Replace this
$ascentPortRange = "Model Portfolio!C4:L20";                // <-- Range or full sheet: Sheet1
$ascentCurrenciesRange = "Curreny Rates!C4:H100"; 

// Fetch data
$ascentModelPortfolio = getStocksCurrency($ascentSpreadsheetId, $ascentPortRange);
$ascentCurrencies = getStocksCurrency($ascentSpreadsheetId, $ascentCurrenciesRange);

            // === CONFIG SECTION ANCHOR  SHEET===
$anchorSpreadsheetId = "1qDXmKxii3QRPX-9w1qGJTOwiPrlrU03vpHITB69aDqA";  // <-- Replace this
$anchorPortRange = "Model Portfolio!C4:L20";                // <-- Range or full sheet: Sheet1
$anchorCurrenciesRange = "Curreny Rates!C4:H100"; 

// Fetch data
$anchorModelPortfolio = getStocksCurrency($anchorSpreadsheetId, $anchorPortRange);
$anchorCurrencies = getStocksCurrency($anchorSpreadsheetId, $anchorCurrenciesRange);*/

// Output result
    ?>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
   
   <div id="calculator-sec" class="">
		<div class="loader-gif">
			<img class="compass-gif" style="display:none;" src="<?php echo site_url(); ?>/wp-content/uploads/2025/11/COMPASS.gif">
			<img class="ascent-gif" style="display:none;" src="<?php echo site_url(); ?>/wp-content/uploads/2025/11/ASCENT.gif">
			<img class="anchor-gif"  style="display:none;" src="<?php echo site_url(); ?>/wp-content/uploads/2025/11/ANCHOR.gif">
		</div>
       <form action="" method="POST" id="stockcal">
            <div class="form-div">
                
                <div class="row">
				<h3>Select Your Portfolio & Currency</h3>
                    <div class="col-sm-4 col-12">
  
                        <label for="exampleFormControlInput1" class="form-label">Portfolio Type</label>
                        <select class="form-select" id="portfolioSelect" aria-label="Default select example">
                          <option selected>Select Portfolio Type</option>
						   <option value="ANCHOR">Anchor</option>
                           <option value="COMPASS">Compass</option>
                          <option value="ASCENT">Ascent</option>
                        </select>
                    </div>
                    <div class="col-sm-4 col-12">
                    <label for="exampleFormControlInput1" class="form-label">Currency</label>
                        <select id="currencySelect" class="form-select" aria-label="Default select example">
                          <option selected>Select Currency</option>
                        <?php foreach($compassCurrencies as $compCurrency){ ?>
                        <option data-min-amount="<?php echo  str_replace(",", "", $compCurrency[5]); ?>" data-currency="<?php echo  $compCurrency[2]; ?>" value="<?php echo  $compCurrency[1]; ?>"> <?php echo  $compCurrency[1]; ?></option>
                        <?php } ?>
                        </select>
                    </div>
                    
                    <div class="col-sm-4 col-12">
                        <label for="exampleFormControlInput1" class="form-label">Investment Amount &nbsp;&nbsp;<div class="inv-amt icon-wrapper">
											<img src="<?php echo site_url(); ?>/wp-content/uploads/2025/11/information-button.png" class="info-icon">
											<div class="tooltip">Indicates the total portfolio value or any smaller periodic investment amount provided by the user.</div>
										</div></label>
                         <input type="number" class="form-control" id="amountInput" placeholder="Enter amount" />
                    </div>
                    <input type="hidden" class="form-control" value="<?php echo site_url(); ?>" id="site-url" />
                </div>
				<div class="col sm-12">
                    <button type="button" class="calculate-btn button-primary">Align My Portfolio</button>
                 </div>
               <div class="row g-4">
                   <!--Compass sheet stock list --->
                   <div id="StockList" class="stock-list table-responsive" style="display:none;">
                        <table class="table">
                            <thead>
                                <tr>
									<th scope="col"> <input class="form-check-input checkAll" type="checkbox" name="" value="" id="checkAll"  checked="checked">&nbsp;&nbsp;&nbsp;Stock &nbsp;
										<div class="icon-wrapper">
											<img src="<?php echo site_url(); ?>/wp-content/uploads/2025/11/information-button.png" class="info-icon">
											<div class="tooltip">Click on stock name to view full report</div>
										</div>
								  </th>
                                  <th scope="col">Ticker</th>
                                  <th scope="col">Sector</th>
                                  <th scope="col">Weekly Closing Price</th>
                                   <th scope="col">MCAP (Crs. INR)</th>
                                  <th scope="col">Allocation(%)</th>
                                  <th scope="col" class="inductive-lbl">Indicative Units
									<div class="ind-unit icon-wrapper2">
											<img src="<?php echo site_url(); ?>/wp-content/uploads/2025/11/information-button.png" class="info-icon">
											<div class="tooltip2">Indicative number of shares for the entered investment amount at the latest available weekly closing price.</div>
										</div>
								  </th>
                                </tr>
                            </thead>
                            <tbody class="table-body">
                               
                            </tbody>
                        </table>
                   </div>
                   
                   
                   
                </div>
                 <div class="col sm-12">
                    <button type="button" id="calculate-btn" class="calculate-btn bottom button-primary"  style="display:none;">Align My Portfolio</button>
                 </div>
            </div>
            
        </form>
        <div class="row align-items-start">
            <div id="resultsArea" class="results-area"></div>
        </div>
    </div>

<script>
    jQuery(document).ready(function(){
		$('#checkAll').on('change', function () {
			$('.stockcheck').prop('checked', $(this).prop('checked'));
			
		});
	
        //show/hide stock checkboxes
    	jQuery("#portfolioSelect").change(function(){
    	    var  portfolio = jQuery(this).val();
			if( portfolio != "Select Portfolio Type"){
            	var  site_url = jQuery("#site-url").val();
				form_data = new FormData();
				form_data.append('portfolio', portfolio);
				
				form_data.append('action', 'get_stock_data');
				//Show Loader
				jQuery(".loader-gif").css("display", "flex");
				
				if(portfolio=='COMPASS'){
					jQuery(".loader-gif .compass-gif").show();
				}else if(portfolio =='ASCENT'){
					jQuery(".loader-gif .ascent-gif").show();
				}else if(portfolio == 'ANCHOR'){
					jQuery(".loader-gif .anchor-gif").show();
				}
				
				jQuery.ajax({
					url: "<?php echo site_url();?>/wp-admin/admin-ajax.php",
					type: 'POST',
					contentType: false,
					processData: false,
					data: form_data,
					success: function (response) {
						
					   // jQuery(".divclass").val(response); //to add value for input
						   //console.log(response);
							var data = JSON.parse(response);
							var tbody = $("#StockList .table-body");
							
							var tr = '';
							data.forEach(function(row) {
								console.log(row[5]);
								
									tr += '<tr>';
									tr += '<td scope="row">';
									tr += '<div class="form-check">';
									tr += '<input class="form-check-input stockcheck" type="checkbox" name="stocks" value="'+row[1]+'" id="" checked/>';
									tr += '<label class="form-check-label" for="checkDefault">';
									tr +=  '<a target="_blank" href="'+site_url+'/report/'+row[1].toLowerCase()+'">'+row[0]+' <i class="fa fa-external-link"></i></a>';
									
									tr += '</label>';
									tr += '</div>';
									tr += '</td>';
									
									
									tr += '<td>'+row[1]+'</td>';
									tr += '<td>'+row[2]+'</td>';
									tr += '<td>'+row[8]+'</td>';
									tr += '<td>'+(row[5] ?? 0)+'</td>';
									tr += '<td>-</td>';
									tr += '<td>-</td>';
									tr += '</tr>';
							});
							
							
								 tbody.html(tr); //insert stocks data in table body
							
							
							
							//Hide Loader
							jQuery(".loader-gif").css("display", "none");
					
							if(portfolio=='COMPASS'){
								jQuery(".loader-gif .compass-gif").hide();
							}else if(portfolio =='ASCENT'){
								jQuery(".loader-gif .ascent-gif").hide();
							}else if(portfolio == 'ANCHOR'){
								jQuery(".loader-gif .anchor-gif").hide();
								
							}
							jQuery("#StockList").show();
							jQuery("#calculate-btn").show();
							
							jQuery('#checkAll').prop('checked', true);

						
					}
		
				});

			}
            
    	});

	
	
    	//run ajax on submit button click
    	jQuery(".calculate-btn").on('click', function(){
    		var  site_url = jQuery("#site-url").val();
    		var  portfolio = jQuery("#portfolioSelect").val();
    		var currency = jQuery("#currencySelect").val();
			var minAmount = jQuery("#currencySelect option:selected").data('min-amount');
			var SelCurrency = jQuery("#currencySelect option:selected").data('currency');
			
			if(portfolio =='Select Portfolio Type'){
				alert('Please select portfolio type.');
				return false;
			}
			if(currency =='Select Currency'){
				alert('Please select currency.');
				return false;
			}
    		var amount = jQuery("#amountInput").val();
			
			if(amount ==''){
				alert('Please enter investment amount.');
				return false;
			}
			else if(amount < minAmount){
				alert('Please enter minimum '+SelCurrency+' '+ minAmount +'/- in investment amount');
				return false;
			}
    		let stockCheck = [];
            $('input[name="stocks"]:checked').each(function(){
              stockCheck.push($(this).val());
            });
    
            if (stockCheck.length > 0) {
             var stocks = stockCheck.join(', ');
            } else {
             var stocks = 'No Stock Selected'
            }
            form_data = new FormData();
            form_data.append('portfolio', portfolio);
            form_data.append('currency', currency);
            form_data.append('amount', amount);
            form_data.append('stocks', stocks);
            form_data.append('action', 'run_stock_sheet_process');
			//Show Loader
			jQuery(".loader-gif").css("display", "flex");
			
			if(portfolio=='COMPASS'){
                jQuery(".loader-gif .compass-gif").show();
			}else if(portfolio =='ASCENT'){
				jQuery(".loader-gif .ascent-gif").show();
			}else if(portfolio == 'ANCHOR'){
				jQuery(".loader-gif .anchor-gif").show();
			}
			
			
			
			
            jQuery.ajax({
                url: "<?php echo site_url();?>/wp-admin/admin-ajax.php",
                type: 'POST',
                contentType: false,
                processData: false,
                data: form_data,
                success: function (response) {
                    
    			   // jQuery(".divclass").val(response); //to add value for input
    				   // console.log(response);
    					var data = JSON.parse(response);
    				    var tbody = $("#StockList .table-body");
    			
                        var tr = '';
                        data.finalStockData.forEach(function(row) {
                           // console.log(row[5]);
                            
                                tr += '<tr>';
                                tr += '<td scope="row">';
                                tr += '<div class="form-check">';
                                tr += '<input class="form-check-input stockcheck" type="checkbox" name="stocks" value="'+row[1]+'" id="" ' + (row[5] == 'Yes' ? "checked" : "") + ' />';
                                tr += '<label class="form-check-label" for="checkDefault">';
                                tr +=  '<a target="_blank" href="'+site_url+'/report/'+row[1].toLowerCase()+'">'+row[0]+' <i class="fa fa-external-link"></i></a>';
							
                                tr += '</label>';
                                tr += '</div>';
                                tr += '</td>';
                                
                                
                                tr += '<td>'+row[1]+'</td>';
                                tr += '<td>'+row[2]+'</td>';
                                tr += '<td>'+row[3]+'</td>';
                                tr += '<td>'+(row[8] ?? 0)+'</td>';
                                tr += '<td>'+row[4]+'</td>';
                                tr += '<td>'+row[7]+'</td>';
                                tr += '</tr>';
                        });
									let actualAmount = data.cashData[2][0]; // value is undefined
									let actualAmounts;

									if (actualAmount === undefined || actualAmount =='') {
										actualAmounts = '-';
									} else {
										actualAmounts = actualAmount;
									}
						
								tr += '<tr><td colspan="2"><strong>'+data.cashData[0][0]+'</strong></td> <td style="text-align: right;">'+actualAmounts+'</td> <td colspan="3" style="border-left:1px solid #ddd;"><strong>'+data.cashData[0][3]+'<s/trong></td><td style="text-align: right;">'+data.cashData[3][3].replace("₹", "");+'</td><tr>';
								
                      
                             tbody.html(tr); //insert stocks data in table body
                      
						
						
						//Hide Loader
						jQuery(".loader-gif").css("display", "none");
				
						if(portfolio=='COMPASS'){
							jQuery(".loader-gif .compass-gif").hide();
						}else if(portfolio =='ASCENT'){
							jQuery(".loader-gif .ascent-gif").hide();
						}else if(portfolio == 'ANCHOR'){
							jQuery(".loader-gif .anchor-gif").hide();
						}
						
						
                        
    				
                }
    
            });
			
        });
    
       /* //run ajax on checkbox
        jQuery('body').on("change", ".stockcheck", function(){
    		var  portfolio = jQuery("#portfolioSelect").val();
    		var currency = jQuery("#currencySelect").val();
    		var amount = jQuery("#amountInput").val();
    
    		let stockCheck = [];
            $('input[name="stocks"]:checked').each(function(){
              stockCheck.push($(this).val());
            });
    
            if (stockCheck.length > 0) {
             var stocks = stockCheck.join(', ');
            } else {
             var stocks = 'No Stock Selected'
            }
            form_data = new FormData();
            form_data.append('portfolio', portfolio);
            form_data.append('currency', currency);
            form_data.append('amount', amount);
            form_data.append('stocks', stocks);
            form_data.append('action', 'run_stock_sheet_process');
    
            jQuery.ajax({
                url: "<?php echo site_url();?>/wp-admin/admin-ajax.php",
                type: 'POST',
                contentType: false,
                processData: false,
                data: form_data,
                success: function (response) {
                    
    			   // jQuery(".divclass").val(response); //to add value for input
    				   // console.log(response);
    					var data = JSON.parse(response);
    				    var tbody = $("#table-body");
                        var tr = '';
                        data.forEach(function(row) {
                            console.log(row[5]);
                            
                                tr += '<tr>';
                                tr += '<td scope="row">';
                                tr += '<div class="form-check">';
                                tr += '<input class="form-check-input stockcheck" type="checkbox" name="stocks" value="'+row[1]+'" id="stockCheck" ' + (row[5] == 'Yes' ? "checked" : "") + ' />';
                                tr += '<label class="form-check-label" for="checkDefault">';
                                tr += row[0];
                                tr += '</label>';
                                tr += '</div>';
                                tr += '</td>';
                                
                                
                                tr += ' <td>'+row[6]+'</td>';
                                tr += '<td>'+row[7]+'</td>';
                                tr += '</tr>';
                        });
                         tbody.html(tr);
    				
                }
    
            });
        });
        */
    
    
    });
</script>
    <?php
    return ob_get_clean();
}

add_shortcode('stock_calculator_form', 'stock_calculator_form');


add_action('wp_ajax_nopriv_get_stock_data', 'get_stock_data');
add_action('wp_ajax_get_stock_data', 'get_stock_data');

function get_stock_data(){
    ob_start();
	 // === CONFIG SECTION COMPASS SHEET===
	if($_POST['portfolio'] == 'COMPASS')
	{
           
		$compasSpreadsheetId = "1QsDYh3CaB-m3ehOovu_dXBb8kBfR-TsywHfNLVzaAmk";  // <-- Replace this
		$compasPortRange = "Model Portfolio!C4:L20";                // <-- Range or full sheet: Sheet1
		$compasCurrenciesRange = "Curreny Rates!C4:H100"; 

		// Fetch data
		$compassModelPortfolio = getStocksCurrency($compasSpreadsheetId, $compasPortRange);
		$compassCurrencies = getStocksCurrency($compasSpreadsheetId, $compasCurrenciesRange);
		$jsonString = json_encode($compassModelPortfolio);

        // Output the JSON string
        echo $jsonString; exit;
	}
	// === CONFIG SECTION ASCENT SHEET===
	if($_POST['portfolio'] == 'ASCENT')
	{
				
		$ascentSpreadsheetId = "1RFMxbpIuo2qfYPwaG9v93IwyMf2cwsxSRnwJzfw_ydA";  // <-- Replace this
		$ascentPortRange = "Model Portfolio!C4:L20";                // <-- Range or full sheet: Sheet1
		$ascentCurrenciesRange = "Curreny Rates!C4:H100"; 

		// Fetch data
		$ascentModelPortfolio = getStocksCurrency($ascentSpreadsheetId, $ascentPortRange);
		$ascentCurrencies = getStocksCurrency($ascentSpreadsheetId, $ascentCurrenciesRange);
		$jsonString = json_encode($ascentModelPortfolio);

        // Output the JSON string
        echo $jsonString; exit;
	}
	// === CONFIG SECTION ANCHOR  SHEET===
	if($_POST['portfolio'] == 'ANCHOR')
	{
				
		$anchorSpreadsheetId = "1qDXmKxii3QRPX-9w1qGJTOwiPrlrU03vpHITB69aDqA";  // <-- Replace this
		$anchorPortRange = "Model Portfolio!C4:L20";                // <-- Range or full sheet: Sheet1
		$anchorCurrenciesRange = "Curreny Rates!C4:H100"; 

		// Fetch data
		$anchorModelPortfolio = getStocksCurrency($anchorSpreadsheetId, $anchorPortRange);
		$anchorCurrencies = getStocksCurrency($anchorSpreadsheetId, $anchorCurrenciesRange);
		$jsonString = json_encode($anchorModelPortfolio);

        // Output the JSON string
        echo $jsonString; exit;
	}

}



/* Get data from google sheet */

function getStocksCurrency($spreadsheetId, $range)
{
    // Initialize Google client
    $client = new Client();
  $cred = __DIR__ . '/credentials.json';
    $client->setAuthConfig($cred);  // Path to your downloaded service account JSON
    $client->addScope(Sheets::SPREADSHEETS_READONLY);

    // Sheets service
    $service = new Sheets($client);

    // Fetch the sheet values
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $values = $response->getValues();

    return $values;
}