# Pair of employees who have worked together

## Task

Create an application that identifies the pair of employees who have worked
together on common projects for the longest period of time.

### Input data

A CSV file with data in the following format:

```
EmpID, ProjectID, DateFrom, DateTo
```

Sample data:

```
143, 12, 2013-11-01, 2014-01-05
218, 10, 2012-05-16, NULL
143, 10, 2009-01-01, 2011-04-27
...
```

Sample output:

```
143, 218, 8
```

### Specific requirements

1. `DateTo` can be `NULL`, equivalent to today.
2. The input data must be loaded to the program from a CSV file.
3. The task solution needs to be uploaded in github.com, repository name must
   be in format: `{FirstName}-{LastName}-employees`.

### Bonus points

1. Create a UI: the user picks up a file from the file system and, after
   selecting it, all common projects of the pair are displayed in a datagrid
   with the following columns: Employee ID #1, Employee ID #2, Project ID,
   Days worked.
2. More than one date format to be supported, extra points will be given if
   all date formats are supported.

## Running the app

Start the container:

```
docker compose up -d
```

### Option 1: CLI

Drop a CSV file into `storage/app/csv-input/` (a few sample files are
already included there), then run the import command:

```
docker exec -it sirma-employees php artisan employees:import
```

It lets you pick which file to import, then prints the winning pair and
their per-project breakdown.

### Option 2: Web UI (bonus)

Open [http://localhost:8000](http://localhost:8000) in a browser, pick a CSV
file from your file system, and upload it. The winning pair and a datagrid
of their common projects (Employee ID #1, Employee ID #2, Project ID, Days
worked) are shown once processing finishes.

## Running the tests

```
docker compose exec app php artisan test
```
