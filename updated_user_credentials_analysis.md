# 🔐 Updated BSIT LMS User Credentials Analysis

## 📋 Database Analysis: `lms_neust_normalized (6).sql`

### 🔑 **Password Storage Method**
- **All passwords are HASHED** using PHP's `password_hash()` function
- **NOT stored as plain text** for security reasons
- **Default password**: `password123` (but stored as hash)

---

## 👥 **Complete User List (131 Total Users)**

### **🔐 Password Hash Information**
- **Hash Algorithm**: `$2y$10$` (bcrypt with cost factor 10)
- **Most Common Hash**: `$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6` (for most students)
- **Admin Hash**: `$2y$10$9dBJLQrfknEAO922pc6sE.ol/dc9DVv.ZIQI7Zt/te3JCETbEO1cG` (Raymond Salvador)
- **Teacher Hash**: `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` (most teachers)

---

## 👨‍💼 **ADMINISTRATORS (1)**

| ID | Username | Email | Password Hash | Full Name | Role | Access Level |
|----|----------|-------|---------------|-----------|------|--------------|
| 1 | mon | salvador@gmail.com | `$2y$10$9dBJLQrfknEAO922pc6sE.ol/dc9DVv.ZIQI7Zt/te3JCETbEO1cG` | Raymond Salvador | admin | super_admin |

---

## 👨‍🏫 **TEACHERS (35)**

| ID | Username | Email | Password Hash | Full Name | Department | Teacher ID |
|----|----------|-------|---------------|-----------|------------|------------|
| 2 | aga | puesca@gmail.com | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Lawrence Puesca | - | NEUST-MGT(TCH)-00001 |
| 3 | jl | eusebio@gmail.com | `$2y$10$SRqOR/5Wig75yS38lFwaZuglCi4/GPnmFRNvRPHKMUF37de5WLsOq` | John Lloyd Eusebio | - | NEUST-MGT(TCH)-00002 |
| 6 | prof_smith | smith@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Dr. Sarah Smith | Computer Science | NEUST-MGT(TCH)-00003 |
| 7 | prof_johnson | johnson@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Prof. Michael Johnson | Information Technology | NEUST-MGT(TCH)-00004 |
| 8 | prof_wilson | wilson@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Dr. Emily Wilson | Computer Science | NEUST-MGT(TCH)-00005 |
| 9 | prof_brown | brown@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Prof. David Brown | Information Technology | NEUST-MGT(TCH)-00006 |
| 10 | prof_davis | davis@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Dr. Lisa Davis | Computer Science | NEUST-MGT(TCH)-00007 |
| 11 | prof_miller | miller@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Prof. Robert Miller | Information Technology | NEUST-MGT(TCH)-00008 |
| 12 | prof_garcia | garcia@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Dr. Maria Garcia | Computer Science | NEUST-MGT(TCH)-00009 |
| 13 | prof_martinez | martinez@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Prof. Carlos Martinez | Information Technology | NEUST-MGT(TCH)-00010 |
| 14 | prof_rodriguez | rodriguez@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Dr. Ana Rodriguez | Computer Science | NEUST-MGT(TCH)-00011 |
| 15 | prof_lee | lee@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Prof. James Lee | Information Technology | NEUST-MGT(TCH)-00012 |
| 67 | prof_anderson | anderson@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Dr. Jennifer Anderson | Computer Science | NEUST-MGT(TCH)-00013 |
| 68 | prof_taylor | taylor@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Prof. Christopher Taylor | Information Technology | NEUST-MGT(TCH)-00014 |
| 69 | prof_thomas | thomas@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Dr. Amanda Thomas | Computer Science | NEUST-MGT(TCH)-00015 |
| 70 | prof_white | white@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Prof. Daniel White | Information Technology | NEUST-MGT(TCH)-00016 |
| 71 | prof_harris | harris@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Dr. Sarah Harris | Computer Science | NEUST-MGT(TCH)-00017 |
| 72 | prof_martin | martin@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Prof. Kevin Martin | Information Technology | NEUST-MGT(TCH)-00018 |
| 73 | prof_thompson | thompson@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Dr. Michelle Thompson | Computer Science | NEUST-MGT(TCH)-00019 |
| 74 | prof_garcia2 | garcia2@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Prof. Anthony Garcia | Information Technology | NEUST-MGT(TCH)-00020 |
| 75 | prof_martinez2 | martinez2@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Dr. Jessica Martinez | Computer Science | NEUST-MGT(TCH)-00021 |
| 76 | prof_robinson | robinson@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Prof. Matthew Robinson | Information Technology | NEUST-MGT(TCH)-00022 |
| 77 | prof_clark | clark@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Dr. Nicole Clark | Computer Science | NEUST-MGT(TCH)-00023 |
| 78 | prof_rodriguez2 | rodriguez2@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Prof. Andrew Rodriguez | Information Technology | NEUST-MGT(TCH)-00024 |
| 79 | prof_lewis | lewis@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Dr. Stephanie Lewis | Computer Science | NEUST-MGT(TCH)-00025 |
| 80 | prof_lee2 | lee2@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Prof. Ryan Lee | Information Technology | NEUST-MGT(TCH)-00026 |
| 81 | prof_walker | walker@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Dr. Lauren Walker | Computer Science | NEUST-MGT(TCH)-00027 |
| 82 | prof_hall | hall@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Prof. Brandon Hall | Information Technology | NEUST-MGT(TCH)-00028 |
| 83 | prof_allen | allen@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Dr. Rachel Allen | Computer Science | NEUST-MGT(TCH)-00029 |
| 84 | prof_young | young@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Prof. Justin Young | Information Technology | NEUST-MGT(TCH)-00030 |
| 85 | prof_king | king@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Dr. Samantha King | Computer Science | NEUST-MGT(TCH)-00031 |
| 86 | prof_wright | wright@neust.edu.ph | `$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O` | Prof. Tyler Wright | Information Technology | NEUST-MGT(TCH)-00032 |

---

## 👨‍🎓 **STUDENTS (95)**

### **Students with Standard Hash (password123)**
**Hash**: `$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6`

| ID | Username | Email | Full Name | Student ID | Section |
|----|----------|-------|-----------|------------|---------|
| 4 | jj | espiritu@gmail.com | John Joseph Espiritu | NEUST-MGT(STD)-00001 | 1st Year A |
| 5 | mj | delacruz@gmail.com | Mark James Dela Cruz | NEUST-MGT(STD)-00002 | 1st Year A |
| 16-55 | student001-student040 | student001@neust.edu.ph - student040@neust.edu.ph | Various Names | NEUST-MGT(STD)-00003 to NEUST-MGT(STD)-00042 | Various Sections |
| 87-131 | student042-student085 | student042@neust.edu.ph - student085@neust.edu.ph | Various Names | NEUST-MGT(STD)-00044 to NEUST-MGT(STD)-00087 | Various Sections |

### **Student with Different Hash**
| ID | Username | Email | Password Hash | Full Name | Student ID |
|----|----------|-------|---------------|-----------|------------|
| 66 | ken | ken@gmail.com | `$2y$10$zZ62B6cCQmBmY/w2UGWYgudeOmvDKZ10CUTVKDTA.ic2tnVtCXGWa` | Ken Kaneki | NEUST-MGT(STD)-00043 |

---

## 📊 **Database Summary**

### **Total Users: 131**
- **Administrators**: 1
- **Teachers**: 35
- **Students**: 95

### **Password Hash Distribution:**
- **Standard Student Hash**: 94 students (`$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6`)
- **Admin Hash**: 1 user (`$2y$10$9dBJLQrfknEAO922pc6sE.ol/dc9DVv.ZIQI7Zt/te3JCETbEO1cG`)
- **Teacher Hash**: 34 teachers (`$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O`)
- **Unique Hash**: 2 users (John Lloyd Eusebio, Ken Kaneki)

### **Section Distribution:**
- **1st Year**: 4 sections (A, B, C, D) - 95 students total
- **2nd Year**: 4 sections (A, B, C, D) - 0 students (empty)
- **3rd Year**: 4 sections (A, B, C, D) - 0 students (empty)
- **4th Year**: 4 sections (A, B, C, D) - 0 students (empty)

---

## 🔐 **Login Information**

### **To Login:**
1. **Username**: Use the `username` field
2. **Email**: Use the `email` field
3. **Password**: `password123` (for most users)

### **Special Cases:**
- **Admin (mon)**: Password unknown (different hash)
- **Teacher (jl)**: Password unknown (different hash)
- **Student (ken)**: Password unknown (different hash)

---

## ⚠️ **Security Note**

**The passwords are NOT stored as plain text!** They are hashed using PHP's `password_hash()` function with bcrypt algorithm. This is the correct and secure way to store passwords in a database. The actual passwords cannot be retrieved from the hashes - they can only be verified when a user logs in by comparing the input password with the stored hash.

To test login, you would need to know the original passwords that were used to create these hashes.
