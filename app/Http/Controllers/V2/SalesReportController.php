<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\AeroToken;
use Illuminate\Http\Request;

class SalesReportController extends Controller
{
    public function get_sales_report($token_id, $date_from, $date_to)
    {
        $aero_token = AeroToken::find($token_id);
        $command = "sr/" . date('dMY', strtotime($date_from)) . '/' . date('dMY', strtotime($date_to)) . '[c/rpt.csv]';

        // return response()->json(['status' => 'ok']);

        $sales_report = cache()->remember($token_id . '_' . $command, now()->addMinutes(5), function () use ($command, $aero_token, $token_id, $date_from, $date_to) {
            $response = $aero_token->build()->runCommand($command, false);

            $csv_text = $response->response;
            $csv_text = str_replace('STOREREPORTFILERPT.CSV', '', $csv_text);

            $csvFileName = "sales_report_" . $token_id . date('dMY', strtotime($date_from)) . '_' . date('dMY', strtotime($date_to)) . '.csv';
            $csvFile = public_path(path: 'csv/' . $csvFileName);

            file_put_contents($csvFile, $csv_text);

            $result = $this->readCSV($csvFile, ',');


            unset($result[1]);

            $sales_report = [];

            $index = 0;
            foreach ($result as $row) {
                $record = [];

                if ($index == 0) {
                    $record[] = '#';
                } else {
                    $record[] = (string) $index;
                }

                foreach ($row as $cell) {
                    $record[] = trim($cell);
                }

                $sales_report[] = $record;
                $index++;
            }

            return $sales_report;
        });
        return response()->json($sales_report, 200);

        // return asset('csv/' . $csvFileName);
    }

    private function readCSV($csvFile, $delimiter = ',')
    {
        $file_handle = fopen($csvFile, 'r');

        while ($csvRow = fgetcsv($file_handle, null, $delimiter)) {
            $line_of_text[] = $csvRow;
        }

        fclose($file_handle);

        return $line_of_text;
    }
}
