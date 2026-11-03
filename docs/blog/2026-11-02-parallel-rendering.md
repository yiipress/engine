---
title: Parallel rendering
summary: Why independent rendering processes give each worker its own PHAR archive handle.
draft: true
---

## Give each rendering worker its own archive

YiiPress can render pages in parallel. Each worker is a separate process that handles rendering jobs.

Starting workers with a process fork caused a problem with PHAR files. A child process inherits the parent's open file
handles. If both processes read the same PHAR handle, they share its read position. Concurrent class loading can then
read the wrong bytes and report an archive error, such as a CRC32 checksum mismatch.

The rendering pool uses `proc_open()` to start independent processes instead. Each worker opens its own package,
sets up the parts needed for rendering, receives a job, and writes separate output files.
The workers do not share a PHAR read position. This approach also works on Windows.

```mermaid
flowchart TD
    accTitle: Each worker has its own PHAR read position
    accDescr: The parent starts two independent workers with proc_open. Each worker opens its own archive handle and writes separate output files.
    parent["Parent build process"] -->|proc_open| first["Worker 1"]
    parent -->|proc_open| second["Worker 2"]
    first --> archive1[("PHAR handle 1")]
    second --> archive2[("PHAR handle 2")]
    archive1 --> output1["Output files for job 1"]
    archive2 --> output2["Output files for job 2"]
    classDef process fill:#ede9fe,stroke:#7c3aed,color:#2e1065
    classDef archive fill:#dbeafe,stroke:#2563eb,color:#172554
    classDef output fill:#dcfce7,stroke:#15803d,color:#14532d
    class parent,first,second process
    class archive1,archive2 archive
    class output1,output2 output
```

The two handles read the same packaged code, but each has its own read position.

Starting and setting up processes takes time. YiiPress therefore renders small batches sequentially when they fall below
its worker thresholds.

For worker jobs and build stages, see [Engine](../engine.md).
