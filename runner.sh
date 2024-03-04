#!/bin/bash


for i in {1..50}
do
    echo "Running iteration $i"

    # 1. Clean up the output directory
    rm -rf output/*

    # 1. Specify your PHP file
    php_file="Benchmark_ILB.php"

    # 2. Specify the file to append to
    output_file="output.txt"

    # 3. Execute the PHP script (modify if you need to pass arguments)
    php "$php_file"

    number_to_append=$(awk '{sum+=$0; count++} END {print sum/count}' output/*)
    string_to_append=",${number_to_append}"

    # 5. Append the number to the last line of the file.
    sed -i "$ s/$/$string_to_append/" $output_file
done
