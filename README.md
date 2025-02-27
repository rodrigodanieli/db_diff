# Database Comparison

## Overview

This project compares database structures across different servers. It automatically generates an update script and a rollback script for database synchronization.

The code compares the following structures:

- Tables (`tables`)
- Views (`views`)
- Events (`events`)
- Procedures (`procedures`)
- Triggers (`triggers`)
- Functions (`functions`)

The comparison process can be parallelized, allowing execution with multiple workers.

---

## Installation

To install the project, follow these steps:

```sh
git clone https://github.com/rodrigodanieli/db_diff.git
cd db_diff
pip install -r requirements.txt
```

Make sure you have Python and the required packages installed.

---

## Configuration

The configuration file must be saved at `config/database_config.json`. This file should contain database connection credentials and the definition of databases and tables to be compared.

### Example of `database_config.json`

```json
{
    "databases": {
        "database_1": {
            "db_type": "mysql",
            "connection": {
                "host": "host_1.com.br",
                "pass": "XXXXXXX",
                "port": 3306,
                "user": "user"
            }
        },
        "database_2": {
            "db_type": "mysql",
            "connection": {
                "host": "host_2.com.br",
                "pass": "YYYYYYY",
                "port": 3306,
                "user": "user_2"
            }
        }
    },
    "bases_tables": {
        "base_1": [
            {
                "table": "table_1",
                "create_data": 1,
                "active": 1
            },
            {
                "table": "table_2",
                "create_data": 0,
                "active": 1
            }
        ]
    }
}
```

### Parameter Explanation

- `databases`: Contains connection definitions for the databases to be compared.
- `db_type`: Type of database (e.g., `mysql`).
- `connection`: Required connection details.
- `bases_tables`: Defines which tables should be compared.
  - `table`: Table name.
  - `create_data`: If `1`, includes data in the comparison; if `0`, compares only the structure.
  - `active`: Defines whether the table should be compared (`1` for enabled, `0` for disabled).

---

## Running the Comparator

To run the comparator, use the following command:

```sh
bin/automation diff:[STRUCTURE] -w [WORKERS] [BASE] [COMP]
```

### Command Parameters

- **`[STRUCTURE]`**: Type of structure to compare (`tables`, `views`, `events`, `procedures`, `triggers`, `functions`).
- **`-w [WORKERS]`**: Number of parallel processes for execution.
- **`[BASE]`**: Name of the database used as the reference for comparison.
- **`[COMP]`**: Name of the database to be updated.

### Example Usage

```sh
bin/automation diff:tables -w 4 database_1 database_2
```

In this example:

- The comparator is checking `tables`.
- It uses 4 workers for parallelism.
- It compares `database_1` with `database_2`, generating update and rollback scripts.

---

## Outputs and Results

Execution generates two main files:

1. **`update_script.sql`** - Contains commands to update `COMP` based on `BASE`.
2. **`rollback_script.sql`** - Contains commands to revert the update if needed.

These files are automatically generated in the configured output directory.

---

## Contribution

Contributions are welcome! To collaborate:

1. Fork this repository.
2. Create a branch for your feature (`git checkout -b my-feature`).
3. Commit your changes (`git commit -m 'Add new feature'`).
4. Push to the repository (`git push origin my-feature`).
5. Open a Pull Request.

---

## License

This project is licensed under the [MIT License](LICENSE).

---

## Contact

For questions or suggestions, reach out via [your email or GitHub].

